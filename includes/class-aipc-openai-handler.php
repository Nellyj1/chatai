<?php
class AIPC_OpenAI_Handler {
    
    private $api_key;
    private $model;
    private $max_tokens = 1000;
    private $temperature = 0.7;
    
    public function __construct() {
        $this->api_key = get_option('aipc_openai_api_key');
        $this->model = get_option('aipc_openai_model', 'gpt-4');
        $this->provider = get_option('aipc_api_provider', 'openai');
        $this->api_base = trim(get_option('aipc_api_base', ''));
        if (empty($this->api_base)) {
            $this->api_base = ($this->provider === 'openrouter') ? 'https://openrouter.ai/api/v1' : 'https://api.openai.com/v1';
        }
    }

    private function is_current_locale_english() {
        $locale = function_exists('pll_current_language') ? pll_current_language('locale') : (function_exists('determine_locale') ? determine_locale() : get_locale());
        return (strpos((string)$locale, 'en') === 0);
    }
    
    public function process_message($message, $conversation_id = null) {
        // Basic PII masking before any processing/storage
        $masked_message = $this->mask_personal_data($message);
        // Guided skin test flow should work regardless of API key presence
        // Default to false if no license to avoid upgrade prompts
        $has_license = false;
        if (class_exists('AIPC_License')) {
            $lic = \AIPC_License::getInstance();
            $has_license = ($lic->is_active() && $lic->has_feature('custom_skin_test'));
        }
        $default_enabled = $has_license ? true : false;
        $skin_test_enabled = get_option('aipc_enable_skin_test', $default_enabled);
        
        // Always check for product quiz keywords, even if feature is disabled
        if (class_exists('AIPC_Skin_Test')) {
            // Specific quiz keywords - exclude standalone 'test' to avoid false positives
            $test_keywords = '/\bproduct\s*quiz\b|\bproduct\s*test\b|\bquestionnaire\b|\baanbeveling\b|\badvies\b|start.*test|\bhuidtest\b|\bskin\s*test\b|nieuwe huidtest/i';
            $want_test = (bool) preg_match($test_keywords, $masked_message);
            $in_test = isset($_POST['skin_test_active']) ? (bool) $_POST['skin_test_active'] : false;
            
            // Show upgrade message if quiz requested but no license
            if (($want_test || $in_test) && !$has_license) {
                $is_en = $this->is_current_locale_english();
                $msg = $is_en
                    ? "🎯 The product quiz is a premium feature! With a Business or Enterprise license, you get personalized product recommendations based on your specific needs and preferences. Contact us to learn more!"
                    : "🎯 De product quiz is een premium feature! Met een Business of Enterprise licentie krijg je gepersonaliseerde productaanbevelingen op basis van jouw specifieke behoeften en voorkeuren. Neem contact op voor meer informatie!";
                $conversation_id = $this->store_conversation($conversation_id, $masked_message, $msg);
                return [
                    'success' => true,
                    'data' => [
                        'message' => $msg,
                        'conversation_id' => $conversation_id,
                        'timestamp' => current_time('mysql')
                    ]
                ];
            }
        }
        
        if ($skin_test_enabled && class_exists('AIPC_Skin_Test')) {
            $skin = new AIPC_Skin_Test();
            // Use same specific quiz keywords as above
            $test_keywords = '/\bproduct\s*quiz\b|\bproduct\s*test\b|\bquestionnaire\b|\baanbeveling\b|\badvies\b|start.*test|\bhuidtest\b|\bskin\s*test\b|nieuwe huidtest/i';
            $want_test = (bool) preg_match($test_keywords, $masked_message);
            $in_test = isset($_POST['skin_test_active']) ? (bool) $_POST['skin_test_active'] : false;

            // License gating for skin test / product quiz
            $license_ok = true;
            if (class_exists('AIPC_License')) {
                $lic = \AIPC_License::getInstance();
                $license_ok = ($lic->is_active() && $lic->has_feature('custom_skin_test'));
            }

            if (($want_test || $in_test) && !$license_ok) {
                $is_en = $this->is_current_locale_english();
                $msg = $is_en
                    ? "The skin test/product quiz is available with a Business or Enterprise license."
                    : "De huidtest/productquiz is beschikbaar met een Business of Enterprise licentie.";
                if (class_exists('AIPC_License')) {
                    $lic = \AIPC_License::getInstance();
                    $upgrade_url = $lic->generate_upgrade_url('business');
                    $msg .= $is_en ? " Upgrade here: " : " Upgrade hier: ";
                    $msg .= $upgrade_url;
                }
                $conversation_id = $this->store_conversation($conversation_id, $masked_message, $msg);
                return [
                    'success' => true,
                    'data' => [
                        'message' => $msg,
                        'conversation_id' => $conversation_id,
                        'timestamp' => current_time('mysql')
                    ]
                ];
            }

            if ($want_test) {
                if (empty($conversation_id)) { $conversation_id = wp_generate_uuid4(); }
                $out = $skin->start($conversation_id);
                $conversation_id = $this->store_conversation($conversation_id, $masked_message, $out['message']);
                return [
                    'success' => true,
                    'data' => [
                        'message' => $out['message'],
                        'conversation_id' => $conversation_id,
                        'timestamp' => current_time('mysql'),
                        'meta' => [
                            'skin_test' => isset($out['skin_test']) ? $out['skin_test'] : null,
                            'quick_replies' => isset($out['quick_replies']) ? $out['quick_replies'] : []
                        ]
                    ]
                ];
            } elseif ($in_test) {
                if (empty($conversation_id)) { $conversation_id = wp_generate_uuid4(); }
                $out = $skin->handle_answer($conversation_id, $masked_message);
                $conversation_id = $this->store_conversation($conversation_id, $masked_message, $out['message']);
                return [
                    'success' => true,
                    'data' => [
                        'message' => $out['message'],
                        'conversation_id' => $conversation_id,
                        'timestamp' => current_time('mysql'),
                        'meta' => [
                            'skin_test' => isset($out['skin_test']) ? $out['skin_test'] : null,
                            'quick_replies' => isset($out['quick_replies']) ? $out['quick_replies'] : []
                        ]
                    ]
                ];
            }
        }
        if (empty($this->api_key)) {
            // Fallback response when no API key is configured
            // But still use product context from database
            $product_context = $this->get_product_context($masked_message);
            $fallback_response = $this->get_fallback_response($masked_message, $product_context);
            
            // Store conversation even without API key
            $conversation_id = $this->store_conversation($conversation_id, $masked_message, $fallback_response);
            
            return [
                'success' => true,
                'data' => [
                    'message' => $fallback_response,
                    'conversation_id' => $conversation_id,
                    'timestamp' => current_time('mysql')
                ]
            ];
        }
        
        // Deterministic answers that shouldn't rely on API
        $lower = strtolower($masked_message);
        
        // Handle standalone 'test' before going to API
        if (preg_match('/^\s*test\s*$/i', $masked_message)) {
            $is_en = $this->is_current_locale_english();
            $response = $is_en 
                ? "I'm not sure what you're looking for. Could you be more specific? I can help with product recommendations, product information, or shopping advice!"
                : "Ik begrijp niet helemaal wat je bedoelt. Kun je je vraag wat specifieker stellen? Ik kan helpen met productaanbevelingen, productinformatie, of algemeen winkeladvies!";
                
            $conversation_id = $this->store_conversation($conversation_id, $masked_message, $response);
            return [
                'success' => true,
                'data' => [
                    'message' => $response,
                    'conversation_id' => $conversation_id,
                    'timestamp' => current_time('mysql')
                ]
            ];
        }
        
        // Handle test-related questions (not standalone 'test') before going to API
        if (preg_match('/\b(testen|uitproberen|proberen)\b/i', $masked_message) && !preg_match('/^\s*test\s*$/i', $masked_message)) {
            $is_en = $this->is_current_locale_english();
            $response = $is_en 
                ? "That's a great idea to test the product first! I'd recommend starting with a small sample or trial if available. Check the product specifications and reviews to see if it meets your needs. You can also look for return policies or satisfaction guarantees. Take your time to evaluate if the product fits your requirements and budget!"
                : "Dat is een goed idee om het product eerst te testen! Ik raad aan om te beginnen met een kleine hoeveelheid of proefverpakking indien beschikbaar. Controleer de productspecificaties en reviews om te zien of het aan je behoeften voldoet. Kijk ook naar retourbeleid of tevredenheidsgaranties. Neem de tijd om te evalueren of het product bij je wensen en budget past!";
                
            $conversation_id = $this->store_conversation($conversation_id, $masked_message, $response);
            return [
                'success' => true,
                'data' => [
                    'message' => $response,
                    'conversation_id' => $conversation_id,
                    'timestamp' => current_time('mysql')
                ]
            ];
        }
        
        // Quick command: show more products (uses verified WooCommerce permalinks)

		$recommendations_enabled = get_option('aipc_enable_product_recommendations', true);
		if ($recommendations_enabled && (strpos($lower, 'toon meer product') !== false || strpos($lower, 'meer product') !== false || strpos($lower, 'more products') !== false)) {
			if (class_exists('WooCommerce')) {
				try {
					// Zorg ervoor dat de skin test class beschikbaar is
					if (!class_exists('AIPC_Skin_Test')) {
						require_once AIPC_PLUGIN_DIR . 'includes/class-aipc-skin-test.php';
					}

					// Valideer conversation_id
					if (empty($conversation_id)) {
						$conversation_id = wp_generate_uuid4();
					}

					// Try to use last skin test profile for this conversation
					$last = get_transient('aipc_skin_last_' . $conversation_id);

					$items = [];
					if (is_array($last) && isset($last['profile']) && is_array($last['profile'])) {
						// Valideer profile data
						$profile = $last['profile'];
						if (!isset($profile['label']) || !is_string($profile['label'])) {
							$profile['label'] = 'Algemeen';
						}
						if (!isset($profile['summary']) || !is_string($profile['summary'])) {
							$profile['summary'] = 'Op basis van je antwoorden adviseren we passende producten.';
						}
						if (!isset($profile['answers']) || !is_array($profile['answers'])) {
							$profile['answers'] = [];
						}

						// Haal huidige teller op voor paginering
						$page_key = 'aipc_more_page_' . $conversation_id;
						$current_page = (int)get_transient($page_key);
						if ($current_page < 1) $current_page = 1;

						// Bereken offset: eerste ronde (finalize) toont 0-4, tweede ronde (page 1) toont 5-14, etc.
						$items_per_page = 10;
						$offset = ($current_page == 1) ? 5 : (5 + ($current_page - 1) * $items_per_page);


						$tester = new AIPC_Skin_Test();
						$more = $tester->recommend_products($profile, $items_per_page, $offset);

						// Update pagina teller voor volgende keer
						set_transient($page_key, $current_page + 1, 2 * HOUR_IN_SECONDS);


						if (is_array($more) && !empty($more)) {
							foreach ($more as $m) {
								// Valideer elk product item
								if (!is_array($m)) {
									continue;
								}

								$raw_name = isset($m['name']) && is_string($m['name']) ? $m['name'] : '';
								if (empty($raw_name)) {
									continue;
								}
								$product_name = html_entity_decode($raw_name, ENT_QUOTES, 'UTF-8');
								$price_text = '';

								// Voeg prijs toe als beschikbaar
								if (isset($m['price']) && !empty($m['price'])) {
									$price = is_numeric($m['price']) ? number_format((float)$m['price'], 2) : $m['price'];
									$price_text = ' (€' . $price . ')';
								}

								// Maak klikbare link
								if (isset($m['url']) && !empty($m['url']) && filter_var($m['url'], FILTER_VALIDATE_URL)) {
									$items[] = '- [' . $product_name . $price_text . '](' . esc_url_raw($m['url']) . ')';
								} else {
									$items[] = '- ' . $product_name . $price_text;
								}
							}
						} else {
							// Als er geen producten meer zijn, reset de pagina teller
							delete_transient($page_key);
						}
					}

					if (empty($items)) {
						// Check if we've shown products before (meaning we're probably done)
						$page_key = 'aipc_more_page_' . $conversation_id;
						$current_page = (int)get_transient($page_key);

                        if ($current_page > 1) {
                            // We've been through pages, so we're probably done
                            $skin_test_enabled = get_option('aipc_enable_skin_test', true);
                            $text = $skin_test_enabled 
                                ? __('Dat waren alle producten die passen bij je voorkeuren! Heb je nog vragen over onze producten, of wil je een nieuwe product quiz doen?', 'ai-product-chatbot')
                                : __('Dat waren alle producten die passen bij jouw criteria! Heb je nog andere vragen over onze producten?', 'ai-product-chatbot');
							delete_transient($page_key); // Reset voor volgende keer
						} else {
							// First time and no profile data
							$text = __('Ik kan geen passende producten vinden. Doe eerst een product quiz om gepersonaliseerde aanbevelingen te krijgen, of stel een specifieke vraag over onze producten.', 'ai-product-chatbot');
						}
					} else {
						$text = __('Meer producten die passen bij je profiel:', 'ai-product-chatbot') . "\n\n" . implode("\n", $items);
					}

					$conversation_id = $this->store_conversation($conversation_id, $masked_message, $text);

					return [
						'success' => true,
						'data' => [
							'message' => $text,
							'conversation_id' => $conversation_id,
							'timestamp' => current_time('mysql')
						]
					];
				} catch (Exception $e) {
					error_log('AIPC Error in toon meer producten: ' . $e->getMessage());
					$text = __('Sorry, er is een fout opgetreden bij het ophalen van meer producten. Probeer het later nog eens.', 'ai-product-chatbot');
					$conversation_id = $this->store_conversation($conversation_id, $masked_message, $text);
					return [
						'success' => true,
						'data' => [
							'message' => $text,
							'conversation_id' => $conversation_id,
							'timestamp' => current_time('mysql')
						]
					];
				}
			} else {
				// WooCommerce is niet actief
				$text = __('Sorry, de productfunctionaliteit is momenteel niet beschikbaar.', 'ai-product-chatbot');
				$conversation_id = $this->store_conversation($conversation_id, $masked_message, $text);
				return [
					'success' => true,
					'data' => [
						'message' => $text,
						'conversation_id' => $conversation_id,
						'timestamp' => current_time('mysql')
					]
				];
			}
		}
		
		if (strpos($lower, 'contact opnemen') !== false || strpos($lower, 'contact op') !== false) {
			$contact_response = get_option('aipc_contact_message', '');
			if (empty($contact_response)) {
				$contact_response = "Op onze contact pagina kun je onze contactgegevens terug vinden.\n\n" .
					"We helpen je graag verder met persoonlijk advies!";
			}

			$conversation_id = $this->store_conversation($conversation_id, $masked_message, $contact_response);
			return [
				'success' => true,
				'data' => [
					'message' => $contact_response,
					'conversation_id' => $conversation_id,
					'timestamp' => current_time('mysql')
				]
			];
		}
        
        // EXPLICIT FIX: Handle the problematic "heb je info/informatie over het ingredient" pattern
        if (preg_match('/\bheb je (info|informatie) over het ingredient/i', $masked_message)) {
            // Force this to use the same processing as "vertel over [ingredient]"
            $masked_message = preg_replace('/\bheb je (info|informatie) over het ingredient\s*/i', 'vertel over ', $masked_message);
        }
        // 0) Check FAQ first (exact/contains match) to avoid API usage for bekende vragen
        $faq_answer = $this->get_faq_answer($masked_message);
        if (!empty($faq_answer)) {
            $conversation_id = $this->store_conversation($conversation_id, $masked_message, $faq_answer);
            return [
                'success' => true,
                'data' => [
                    'message' => $faq_answer,
                    'conversation_id' => $conversation_id,
                    'timestamp' => current_time('mysql')
                ]
            ];
        }
        if ($this->is_product_count_question($lower)) {
            $count = 0;
            $post_counts = wp_count_posts('product');
            if ($post_counts && isset($post_counts->publish)) {
                $count = intval($post_counts->publish);
            }
            $is_en = $this->is_current_locale_english();
            $det_msg = $count > 0
                ? ($is_en ? sprintf('We currently have %d products available in the webshop.', $count)
                          : sprintf('We hebben momenteel %d producten beschikbaar in de webshop.', $count))
                : ($is_en ? 'I cannot retrieve an exact number of products at this moment.'
                          : 'Ik kan op dit moment geen exact aantal producten ophalen.');
            $conversation_id = $this->store_conversation($conversation_id, $masked_message, $det_msg);
            return [
                'success' => true,
                'data' => [
                    'message' => $det_msg,
                    'conversation_id' => $conversation_id,
                    'timestamp' => current_time('mysql')
                ]
            ];
        }

        // Get conversation history
        $conversation_history = $this->get_conversation_history($conversation_id);
        
        // Get relevant product knowledge
        $product_context = $this->get_product_context($masked_message);
        
        // For ingredient questions, enrich context with recently mentioned products
        if ($this->is_ingredient_question(strtolower($masked_message))) {
            $additional_context = $this->get_conversation_product_context($masked_message, $conversation_history);
            if (!empty($additional_context)) {
                $product_context .= "\n\n" . $additional_context;
            }
        }
        

        // Get custom sources (CPT) context
        $cpt_context = $this->get_custom_sources_context($masked_message);
        if (!empty($cpt_context)) {
            $product_context .= (empty($product_context) ? '' : "\n") . $cpt_context;
        }
        
        // Build system prompt
        $system_prompt = $this->build_system_prompt($product_context);
        
        // Voor 'testen' vragen: voeg specifieke instructie toe
        if (preg_match('/\b(test|testen|uitproberen|proberen)\b/i', $masked_message) && !preg_match('/^\s*test\s*$/i', $masked_message)) {
            $system_prompt .= "\n\nSPECIFIEKE INSTRUCTIE: De gebruiker vraagt over het testen/uitproberen van producten. Geef altijd een uitgebreid, behulpzaam antwoord van minimaal 2-3 zinnen met praktische tips. NOOIT een kort woord zoals alleen 'test' antwoorden.";
        }
        
        // Prepare messages for OpenAI
        $messages = [
            ['role' => 'system', 'content' => $system_prompt]
        ];
        
        // Add conversation history
        foreach ($conversation_history as $msg) {
            $messages[] = $msg;
        }
        
        // Add current message
        $messages[] = ['role' => 'user', 'content' => $masked_message];
        
        // License gating for AI responses - require Business+ tier
        $ai_allowed = false;
        if (class_exists('AIPC_License')) {
            $lic = \AIPC_License::getInstance();
            $current_tier = $lic->is_active() ? $lic->get_current_tier() : 'none';
            $ai_allowed = in_array($current_tier, ['business', 'enterprise']);
        }
        
        if (!$ai_allowed) {
            // Fallback to FAQ/WooCommerce response for Basic/No license
            $fallback_response = $this->get_fallback_response($masked_message, $product_context);
            
            // If fallback returns null (ingredient question with AI fallback enabled), provide a generic response
            if ($fallback_response === null) {
                $fallback_response = "Ik begrijp je vraag, maar heb een Business+ licentie nodig om uitgebreide antwoorden te kunnen geven. Kan ik je op een andere manier helpen?";
            }
            
            $conversation_id = $this->store_conversation($conversation_id, $masked_message, $fallback_response);
            
            return [
                'success' => true,
                'data' => [
                    'message' => $fallback_response,
                    'conversation_id' => $conversation_id,
                    'timestamp' => current_time('mysql')
                ]
            ];
        }
        
        // Call OpenAI API (Business+ only)
        $response = $this->call_openai_api($messages, $conversation_id);
        
        if ($response['success']) {
            // Store conversation
            $conversation_id = $this->store_conversation($conversation_id, $masked_message, $response['data']['content']);
            // Track free-tier usage for OpenRouter :free models
            $this->track_free_usage();
            // Log token usage (if provided)
            if (!empty($response['usage']) && is_array($response['usage'])) {
                $this->log_token_usage($conversation_id, $response['usage']);
            }
            
            return [
                'success' => true,
                'data' => [
                    'message' => $response['data']['content'],
                    'conversation_id' => $conversation_id,
                    'timestamp' => current_time('mysql')
                ]
            ];
        }
        
        // Optionally show raw API error in chat (debug setting)
        if (get_option('aipc_show_api_errors', false)) {
            $error_message = isset($response['message']) ? $response['message'] : __('Onbekende API fout', 'ai-product-chatbot');
            $conversation_id = $this->store_conversation($conversation_id, $masked_message, $error_message);
            return [
                'success' => true,
                'data' => [
                    'message' => $error_message,
                    'conversation_id' => $conversation_id,
                    'timestamp' => current_time('mysql')
                ]
            ];
        }
        
        // Try FAQ fallback on API failure - only if has license
        $has_license = false;
        if (class_exists('AIPC_License')) {
            $lic = AIPC_License::getInstance();
            $has_license = $lic->is_active();
        }
        
        if ($has_license) {
            $faq_answer_on_fail = $this->get_faq_answer($masked_message);
            if (!empty($faq_answer_on_fail)) {
                $conversation_id = $this->store_conversation($conversation_id, $masked_message, $faq_answer_on_fail);
                return [
                    'success' => true,
                    'data' => [
                        'message' => $faq_answer_on_fail,
                        'conversation_id' => $conversation_id,
                        'timestamp' => current_time('mysql')
                    ]
                ];
            }
        }

        // Graceful fallback when API fails: use local product/document context + smart defaults
        $fallback_response = $this->get_fallback_response($masked_message, $product_context);
        
        // If fallback returns null, provide a generic error message
        if ($fallback_response === null) {
            $fallback_response = "Sorry, er is een probleem met de AI service. Kan ik je op een andere manier helpen?";
        }
        
        $conversation_id = $this->store_conversation($conversation_id, $masked_message, $fallback_response);
        
        return [
            'success' => true,
            'data' => [
                'message' => $fallback_response,
                'conversation_id' => $conversation_id,
                'timestamp' => current_time('mysql')
            ]
        ];
    }

    private function mask_personal_data($text) {
        if (!is_string($text) || $text === '') { return $text; }
        $maskEmail = (bool) get_option('aipc_mask_email', true);
        $maskPhone = (bool) get_option('aipc_mask_phone', true);
        // Mask emails (fully hide) if enabled
        if ($maskEmail) {
            $text = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[e‑mail verborgen]', $text);
        }
        // Mask NL-style phone numbers (basic) – fully hide if enabled
        if ($maskPhone) {
            $text = preg_replace('/(?:(?:\+|00)31|0)\s?(?:6|[1-9][0-9])(?:[\s-]?[0-9]){7,9}/', '[telefoon verborgen]', $text);
        }
        return $text;
    }

    private function track_free_usage() {
        // Only for OpenRouter free variants (model id ends with :free)
        if ($this->provider !== 'openrouter') {
            return;
        }
        if (is_string($this->model) && substr($this->model, -5) === ':free') {
            $key = 'aipc_or_free_used_' . gmdate('Ymd');
            $used = intval(get_option($key, 0));
            update_option($key, $used + 1);
        }
    }
    
    private function build_system_prompt($product_context) {
        // Optional admin-defined system prompt (per language if available)
        $custom = get_option('aipc_system_prompt', '');
        if ($custom) {
            if (function_exists('pll_translate_string')) {
                $custom = pll_translate_string($custom, 'ai-product-chatbot');
            } elseif (has_filter('wpml_translate_single_string')) {
                $custom = apply_filters('wpml_translate_single_string', $custom, 'ai-product-chatbot', 'aipc_system_prompt');
            }
        }
        $tone = get_option('aipc_chatbot_tone', 'neutral');
        $allow_judgement = get_option('aipc_allow_judgement', false);
        $tips_style = get_option('aipc_tips_style', 'default');

        $prompt = $custom ?: ("Je bent een AI product assistant die klanten helpt bij het vinden van de juiste producten. ".
            "Je helpt met productaanbevelingen, productinformatie en persoonlijke adviezen.\n\n");
        
        $prompt .= "BELANGRIJKE RICHTLIJNEN:\n";
        $prompt .= "- Wees vriendelijk, behulpzaam en professioneel\n";
        $prompt .= "- Geef specifieke productaanbevelingen gebaseerd op klantbehoeften en voorkeuren\n";
        $prompt .= "- Toon MEERDERE PRODUCTEN (3-5 opties) als er verschillende relevante producten beschikbaar zijn\n";
        $prompt .= "- Geef klanten keuzemogelijkheden door verschillende productopties te presenteren\n";
        $prompt .= "- Leg producteigenschappen en specificaties uit in begrijpelijke taal\n";
        $prompt .= "- Vergelijk producten objectief en eerlijk\n";
        $prompt .= "- Vraag door als je meer informatie nodig hebt\n";
        $prompt .= "- Voor medische/huidcondities: vermeld ALLE relevante producten uit de context, voeg disclaimer toe 'Bij aanhoudende klachten raadpleeg een specialist'\n";

        // Tone and judgement controls
        if (!$allow_judgement) {
            $prompt .= "- Vermijd waardeoordelen (zoals 'lang', 'kort', 'duur', 'goedkoop', 'opvallend'); geef uitsluitend feitelijke, neutrale informatie\n";
            $prompt .= "- Rapporteer prijzen, levertijden en voorraad zonder kwalificaties of vergelijkingen\n";
            $prompt .= "- Maak geen aannames over prijs-kwaliteit of marktconformiteit\n";
            $prompt .= "Voorbeeld (niet doen): '€500 is opvallend duur.'\n";
            $prompt .= "Voorbeeld (wel doen): 'De prijs is €500,00. Hier is de productlink: <url>'\n";
        }
        if ($tone === 'neutral') {
            $prompt .= "- Hanteer een neutrale, informatieve toon\n\n";
        } elseif ($tone === 'formal') {
            $prompt .= "- Hanteer een formele, beleefde toon\n\n";
        } elseif ($tone === 'friendly') {
            $prompt .= "- Hanteer een vriendelijke, toegankelijke toon\n\n";
        } else {
            $prompt .= "\n";
        }

        // Catalogus/bronbeperking om hallucinaties te voorkomen
        $prompt .= "CATALOGUSREGELS:\n";
        $prompt .= "- Gebruik uitsluitend producten/links die in de context zijn meegegeven (WooCommerce/FAQ/Documenten)\n";
        $prompt .= "- Noem geen merken of producten die NIET in de context staan\n";
        $prompt .= "- Gebruik alleen de meegegeven product-URL's; maak geen externe of bedachte links\n";
        $prompt .= "- Geef alleen concrete productaanbevelingen wanneer er daadwerkelijke producten in de context staan (secties 'PRODUCTEN:' of 'WOOCOMMERCE PRODUCTEN:')\n";
        $prompt .= "- PRODUCT PRESENTATIE: Als er 3+ relevante producten zijn, toon er tenminste 3-5 om klanten keuzemogelijkheden te geven\n";
        $prompt .= "- Rangschik producten van meest relevant naar minder relevant, maar presenteer meerdere opties\n";
        $prompt .= "- Wanneer je producten noemt, maak altijd klikbare links door de productnaam en prijs te koppelen aan de URL met Markdown syntax\n";
        $prompt .= "  Voorbeeld: 'Ik raad [Productnaam (€12,34)](https://voorbeeld.nl/product/xyz) aan voor jouw huidtype.'\n";
        $prompt .= "  NOOIT: 'Productnaam (€12,34) URL: https://voorbeeld.nl/product/xyz' - dit is niet klikbaar\n";
        $prompt .= "- Als er geen producten in de context staan: zeg dit expliciet en stel 1 verduidelijkende vraag (geen placeholders of generieke 'Bekijk product' regels)\n\n";

        // Ingredient handling instructions
        $allow_ai_ingredient_info = get_option('aipc_allow_ai_ingredient_info', true);
        if ($allow_ai_ingredient_info) {
            $prompt .= "\nINGREDIËNT INFORMATIE:\n";
            $prompt .= "- Voor ingrediëntvragen: gebruik eerst database informatie uit de context als beschikbaar\n";
            $prompt .= "- Als ingrediënt niet in database staat: geef algemene, feitelijke informatie over het ingrediënt\n";
            $prompt .= "- Houd ingrediënt uitleg beknopt maar informatief\n";
            $prompt .= "- BELANGRIJK: Doorzoek alle producten in de 'RELEVANTE PRODUCTINFORMATIE' naar het ingrediënt en vermeld deze producten\n";
            $prompt .= "- Kijk in de 'Ingrediënten:' lijsten van elk product naar variaties zoals 'aloe vera', 'aloë vera', etc.\n";
            $prompt .= "- Als producten het ingrediënt bevatten: maak klikbare links en vermeld deze expliciet\n";
            $prompt .= "- Zeg NOOIT 'geen producten' zonder eerst grondig te zoeken in alle productinformatie\n\n";
        } else {
            $prompt .= "\nINGREDIËNT INFORMATIE:\n";
            $prompt .= "- Gebruik ALLEEN database-gedefinieerde ingrediënt informatie uit de context\n";
            $prompt .= "- Geef GEEN algemene ingrediënt kennis als het ingrediënt niet in de database staat\n";
            $prompt .= "- Bij onbekende ingrediënten: verwijs naar andere manieren om te helpen\n\n";
        }
        
        // Tips format control
        if ($tips_style === 'bullets_3_short') {
            $prompt .= "\nSPECIFIEK VOOR TIPS-VRAGEN:\n- Geef precies 3 bullets\n- Elke bullet maximaal 1 zin\n- Geen extra toelichtingen of afsluiters\n\n";
        } elseif ($tips_style === 'bullets_5_short') {
            $prompt .= "\nSPECIFIEK VOOR TIPS-VRAGEN:\n- Geef precies 5 bullets\n- Elke bullet maximaal 1 zin\n- Geen extra toelichtingen of afsluiters\n\n";
        }
        
        // Append FAQ context (language-aware)
        $faq_context = $this->get_faq_context();
        if (!empty($faq_context)) {
            $prompt .= "ALGEMENE FAQ:\n" . $faq_context . "\n\n";
        }
        
        // Add ingredient database context
        $ingredient_context = $this->get_ingredient_context();
        if (!empty($ingredient_context)) {
            $prompt .= "INGREDIËNT DATABASE:\n" . $ingredient_context . "\n\n";
        }

        if (!empty($product_context)) {
            $prompt .= "RELEVANTE PRODUCTINFORMATIE:\n";
            $prompt .= $product_context . "\n\n";
            
        } else {
        }
        
        // Language directive
        $locale = function_exists('pll_current_language') ? pll_current_language('locale') : determine_locale();
        $langName = strpos($locale, 'nl') === 0 ? 'Nederlands' : (strpos($locale, 'en') === 0 ? 'Engels' : 'de gekozen taal');
        $prompt .= "Antwoord altijd in het " . $langName . " en wees behulpzaam!";
        
        return $prompt;
    }

    private function get_faq_context() {
        global $wpdb;
        $table = $wpdb->prefix . 'aipc_faq';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return '';
        }
        $lang = function_exists('pll_current_language') ? pll_current_language('locale') : determine_locale();
        $limit = max(0, absint(get_option('aipc_max_faq_items', 20)));
        
        // Cache FAQ context for 10 minutes
        $cache_key = 'aipc_faq_context_' . $lang . '_' . $limit;
        $cached_context = wp_cache_get($cache_key, 'aipc_faq');
        if ($cached_context !== false) {
            return $cached_context;
        }
        
        $sql = "SELECT question, answer FROM $table WHERE status = 'active' AND (lang = %s OR lang IS NULL OR lang = '') ORDER BY (lang = %s) DESC, created_at DESC" . ($limit > 0 ? (" LIMIT " . intval($limit)) : "");
        $rows = $wpdb->get_results($wpdb->prepare($sql, $lang, $lang), ARRAY_A);
        if (empty($rows)) {
            wp_cache_set($cache_key, '', 'aipc_faq', 600);
            return '';
        }
        $out = '';
        foreach ($rows as $row) {
            $out .= "Q: " . $row['question'] . "\nA: " . wp_strip_all_tags($row['answer']) . "\n\n";
        }
        
        // Cache for 10 minutes
        wp_cache_set($cache_key, $out, 'aipc_faq', 600);
        
        return $out;
    }
    
    private function get_ingredient_context() {
        global $wpdb;
        $table = $wpdb->prefix . 'aipc_ingredients';
        
        // Check if ingredients table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return '';
        }
        
        // Cache ingredient context for 10 minutes
        $cache_key = 'aipc_ingredient_context';
        $cached_context = wp_cache_get($cache_key, 'aipc_ingredients');
        if ($cached_context !== false) {
            return $cached_context;
        }
        
        $ingredients = $wpdb->get_results(
            "SELECT name, description, benefits FROM $table WHERE status != 'deleted' ORDER BY name ASC LIMIT 50",
            ARRAY_A
        );
        
        if (empty($ingredients)) {
            wp_cache_set($cache_key, '', 'aipc_ingredients', 600);
            return '';
        }
        
        $out = '';
        foreach ($ingredients as $ingredient) {
            $name = $ingredient['name'];
            $description = !empty($ingredient['description']) ? $ingredient['description'] : '';
            $benefits = !empty($ingredient['benefits']) ? $ingredient['benefits'] : '';
            
            if (!empty($description)) {
                $out .= "$name: $description\n";
            } elseif (!empty($benefits)) {
                // Try to decode JSON benefits
                $benefits_decoded = json_decode($benefits, true);
                if (is_array($benefits_decoded)) {
                    $out .= "$name: " . implode('. ', $benefits_decoded) . ".\n";
                } else {
                    $out .= "$name: $benefits\n";
                }
            }
        }
        
        // Cache for 10 minutes
        wp_cache_set($cache_key, $out, 'aipc_ingredients', 600);
        
        return $out;
    }

    private function get_faq_answer($message) {
        global $wpdb;
        $table = $wpdb->prefix . 'aipc_faq';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return '';
        }
        $lang = function_exists('pll_current_language') ? pll_current_language('locale') : determine_locale();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT question, answer FROM $table WHERE status != 'deleted' AND (lang = %s OR lang IS NULL OR lang = '') ORDER BY (lang = %s) DESC, created_at DESC", $lang, $lang), ARRAY_A);
        if (empty($rows)) {
            return '';
        }
        $ml = $this->normalize_text($message);
        $mtokens = $this->tokenize_question($ml);
        foreach ($rows as $row) {
            $q = isset($row['question']) ? $row['question'] : '';
            $a = isset($row['answer']) ? trim($row['answer']) : '';
            if ($q === '' || $a === '') { continue; }
            $ql = $this->normalize_text($q);
            // Quick path: exact normalized match or substring
            if ($ml === $ql || strpos($ml, $ql) !== false || strpos($ql, $ml) !== false) {
                return wp_strip_all_tags($a);
            }
            // Token overlap similarity (Jaccard)
            $qtokens = $this->tokenize_question($ql);
            if (!empty($mtokens) && !empty($qtokens)) {
                $sim = $this->jaccard_similarity($mtokens, $qtokens);
                if ($sim >= 0.5) { // tolerant matching
                    return wp_strip_all_tags($a);
                }
                // Extra heuristic: shared core keyword present in both strings
                if ($this->has_shared_core_keyword($ml, $ql)) {
                    return wp_strip_all_tags($a);
                }
            }
        }
        return '';
    }

    private function normalize_text($text) {
        $t = strtolower(trim($text));
        // Replace punctuation with spaces
        $t = preg_replace('/[\p{P}\p{S}]+/u', ' ', $t);
        // Collapse whitespace
        $t = preg_replace('/\s+/u', ' ', $t);
        return trim($t);
    }

    private function tokenize_question($text) {
        $stop = [
            'de','het','een','en','of','voor','met','op','aan','in','uit','over','onder','tegen','naar','bij','van','tot','als','dan','maar','ook','dus','toch','al','nog','wel','niet','geen','heb','hebt','heeft','hebben','ben','bent','is','zijn','was','waren','wordt','worden','kan','kunnen','wil','wilt','willen','mag','mogen','u','je','jij','we','wij','ze','zij','ik','mij','me','jou','jouw','jullie','wat','hoeveel','bedragen','zijn'
        ];
        $synonyms = [
            'verzendkosten' => 'verzendkosten',
            'verzend' => 'verzendkosten',
            'verzenden' => 'verzendkosten',
            'verzend kosten' => 'verzendkosten',
            'shipping' => 'verzendkosten',
            'kosten' => 'kosten',
            'levering' => 'levering',
            'bezorg' => 'levering',
            'hoogte' => 'hoog',
            'hoog' => 'hoog',
            'prijs' => 'kosten'
        ];
        $words = explode(' ', $text);
        $tokens = [];
        foreach ($words as $w) {
            $w = trim($w);
            if ($w === '' || in_array($w, $stop, true)) continue;
            if (isset($synonyms[$w])) {
                $w = $synonyms[$w];
            }
            $tokens[] = $w;
        }
        // Deduplicate
        return array_values(array_unique($tokens));
    }

    private function jaccard_similarity($a, $b) {
        $setA = array_fill_keys($a, true);
        $setB = array_fill_keys($b, true);
        $intersect = array_intersect_key($setA, $setB);
        $union = $setA + $setB;
        $u = count($union);
        return $u === 0 ? 0.0 : (count($intersect) / $u);
    }

    private function has_shared_core_keyword($a, $b) {
        $core = ['verzendkosten','verzend','verzenden','shipping','bezorg','levering','kosten','prijs'];
        foreach ($core as $k) {
            if (strpos($a, $k) !== false && strpos($b, $k) !== false) {
                return true;
            }
        }
        return false;
    }
    
    private function get_product_context($message) {
        $product_manager = new AIPC_Product_Manager();
        $document_manager = new AIPC_Document_Manager();
        $live_only = true; // Always use live WooCommerce data
        
        // Extract potential product names or ingredients from message
        $keywords = $this->extract_keywords($message);
        
        
        $context = "";
        
        // Special handling for ingredient questions: load ALL products for AI to search through
        $is_ingredient_question = $this->is_ingredient_question(strtolower($message));
        
        // Get relevant products with scoring
        if ($live_only && class_exists('WooCommerce')) {
            if ($is_ingredient_question) {
                // For ingredient questions: use the same search as normal questions to ensure ingredient meta search is used
                $extracted_ingredient = $this->extract_ingredient_from_message(strtolower($message));
                if ($extracted_ingredient) {
                    // Use the full search function that includes ingredient meta search (step 6 in search_woocommerce_products)
                    $products = $this->search_woocommerce_products_scored($extracted_ingredient, [$extracted_ingredient]);
                } else {
                    // If no ingredient extracted, search with the full message
                    $products = $this->search_woocommerce_products_scored($message, $keywords);
                }
                
                // If still no products found, try fallback with recent products
                if (empty($products)) {
                    $args = [
                        'post_type' => 'product',
                        'post_status' => 'publish',
                        'posts_per_page' => 20,
                        'orderby' => 'date',
                        'order' => 'DESC'
                    ];
                    $posts = get_posts($args);
                    $products = [];
                    foreach ($posts as $p) {
                        $prod = wc_get_product($p->ID);
                        if ($prod) {
                            $products[] = $this->normalize_wc_product_entry($prod);
                        }
                    }
                }
            } else {
                // Normal product search for non-ingredient questions
                // Special handling for comparison questions: fetch both sides explicitly
                $compare = $this->extract_comparison_titles($message);
                if (!empty($compare)) {
                    $products = [];
                    foreach ($compare as $title) {
                        $found = $this->search_woocommerce_products_scored($title, $keywords);
                        if (!empty($found)) {
                            // Merge uniquely by product URL
                            foreach ($found as $f) {
                                $key = isset($f['product_url']) ? $f['product_url'] : (isset($f['name']) ? $f['name'] : md5(json_encode($f)));
                                $products[$key] = $f;
                            }
                        }
                    }
                    $products = array_values($products);
                    // If still empty, fall back to message search
                    if (empty($products)) {
                        $products = $this->search_woocommerce_products_scored($message, $keywords);
                    }
                } else {
                    $products = $this->search_woocommerce_products_scored($message, $keywords);
                }
            }
        } else {
            $products = $product_manager->search_products($keywords);
        }
        
        // If no products found and this is a general product question OR vague ingredient question OR medical/skincare question, get a list of Woo products
        $message_lower = strtolower($message);
        $is_vague_ingredient_question = (strpos($message_lower, 'ingredi') !== false && 
                                        (strpos($message_lower, 'welke') !== false || 
                                         strpos($message_lower, 'zitten') !== false ||
                                         strpos($message_lower, 'bevat') !== false));
        
        // Check for medical/skincare terms that need full product context
        $medical_terms = ['voetschimmel', 'eczeem', 'acne', 'psoriasis', 'schimmel', 'huidprobleem', 'huidklachten'];
        $is_medical_question = false;
        foreach ($medical_terms as $term) {
            if (strpos($message_lower, $term) !== false) {
                $is_medical_question = true;
                break;
            }
        }
        
        // For medical questions: ONLY get all products if no specific matches were found
        if (empty($products) && ($is_medical_question || $this->is_general_product_question($message) || $is_vague_ingredient_question)) {
            if ($live_only && class_exists('WooCommerce')) {
                $posts_per_page = ($is_vague_ingredient_question || $is_medical_question) ? 50 : 10;  // More products for medical/ingredient questions
                $args = [
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'posts_per_page' => $posts_per_page,
                    'orderby' => 'date',
                    'order' => 'DESC'
                ];
                $posts = get_posts($args);
                $woo_list = [];
                foreach ($posts as $p) {
                    $prod = wc_get_product($p->ID);
                    if ($prod) {
                        $woo_list[] = $this->normalize_wc_product_entry($prod);
                    }
                }
                $products = $woo_list;
            } else {
                $all_products = $product_manager->get_all_products();
                $products = $all_products;
            }
        }
        
        // If still no products found, try to suggest related terms
        if (empty($products) && !empty($keywords)) {
            $suggestions = $this->get_related_product_suggestions($keywords);
            if (!empty($suggestions)) {
                $context .= "GEEN EXACTE MATCH GEVONDEN:\n";
                $context .= "Geen producten gevonden voor '" . implode(', ', $keywords) . "'.\n";
                $context .= "GERELATEERDE PRODUCTEN BESCHIKBAAR VOOR:\n";
                foreach ($suggestions as $suggestion) {
                    $context .= "- " . $suggestion['term'] . " (" . $suggestion['count'] . " producten)\n";
                }
                $context .= "\n";
            }
        }
        
        if (!empty($products)) {
            $context .= "PRODUCTEN:\n";
            foreach ($products as $product) {
                $context .= "- " . $product['name'] . ": " . $product['description'] . "\n";
                if (!empty($product['ingredients'])) {
                    $context .= "  Ingrediënten: " . implode(', ', $product['ingredients']) . "\n";
                }
                if (!empty($product['skin_types'])) {
                    $context .= "  Geschikt voor: " . implode(', ', $product['skin_types']) . "\n";
                }
                if (isset($product['price']) && is_numeric($product['price']) && $product['price'] > 0) {
                    $context .= "  Prijs: €" . number_format((float)$product['price'], 2) . "\n";
                }
                if (isset($product['stock_status'])) {
                    $context .= "  Voorraad: " . ucfirst($product['stock_status']) . "\n";
                }
                if (isset($product['product_url'])) {
                    $context .= "  URL: " . $product['product_url'] . "\n";
                }
                $context .= "\n";
            }
            
        } else {
        }
        
        // Get relevant ingredients (always search, regardless of live-only mode)
        $ingredients = $product_manager->search_ingredients($keywords);
        if (!empty($ingredients)) {
            $context .= "INGREDIËNTEN:\n";
            foreach ($ingredients as $ingredient) {
                $context .= "- " . $ingredient['name'] . ": " . $ingredient['description'] . "\n";
                if (!empty($ingredient['benefits'])) {
                    $context .= "  Voordelen: " . implode(', ', $ingredient['benefits']) . "\n";
                }
                $context .= "\n";
            }
        }
        
        // Get relevant document content - Enterprise only
        $documents_allowed = false;
        if (class_exists('AIPC_License')) {
            $lic = AIPC_License::getInstance();
            $current_tier = $lic->get_current_tier();
            $documents_allowed = ($current_tier === 'enterprise' && $lic->is_active());
        }
        
        if ($documents_allowed) {
            $documents = $document_manager->search_documents($message);
            if (!empty($documents)) {
                $context .= "DOCUMENTEN:\n";
                foreach ($documents as $document) {
                    $context .= "- " . $document['title'] . ": " . $document['description'] . "\n";
                    $context .= "  Content: " . substr($document['content'], 0, 500) . "...\n\n";
                }
            }
            
            // Also get general document content for broader context
            $document_content = $document_manager->get_document_content_for_ai($message);
            if (!empty($document_content)) {
                $context .= "ALGEMENE PRODUCTINFORMATIE:\n";
                $context .= $document_content . "\n\n";
            }
        }
        
        // Get WooCommerce context if available
        if (class_exists('WooCommerce') && get_option('aipc_woocommerce_enabled', true)) {
            $woo_integration = new AIPC_WooCommerce_Integration();
            $woo_context = $woo_integration->get_woocommerce_product_context($message);
            if (!empty($woo_context)) {
                $context .= $woo_context;
            }
        }
        
        return $context;
    }

    private function render_cpt_template($tpl, $vars, $meta, $maxChars) {
        // Prepare replacements
        $repl = [
            '{title}'   => $vars['title'],
            '{excerpt}' => mb_substr($vars['excerpt'], 0, $maxChars) . (mb_strlen($vars['excerpt']) > $maxChars ? '...' : ''),
            '{content}' => mb_substr($vars['content'], 0, $maxChars) . (mb_strlen($vars['content']) > $maxChars ? '...' : ''),
            '{link}'    => $vars['link'],
        ];
        $out = strtr($tpl, $repl);
        // Meta placeholders {meta:key}
        $out = preg_replace_callback('/\{meta:([a-z0-9_\-]+)\}/i', function ($m) use ($meta) {
            $k = strtolower($m[1]);
            return isset($meta[$k]) ? $meta[$k] : '';
        }, $out);
        return $out;
    }

    private function get_custom_sources_context($userMsg) {
        $sources = get_option('aipc_content_sources', []);
        if (empty($sources) || !is_array($sources)) return '';
        $useWoo = (bool) get_option('aipc_source_use_woocommerce', class_exists('WooCommerce'));

        // License checking for Content Sources - Enterprise only
        $content_license_allowed = false;
        $allowed_basic_types = ['post', 'page', 'product']; // Standard WP types
        
        if (class_exists('AIPC_License')) {
            $lic = AIPC_License::getInstance();
            $current_tier = $lic->get_current_tier();
            $is_active = $lic->is_active();
            
            // Custom Post Types require Enterprise tier
            if ($current_tier === 'enterprise' && $is_active) {
                $content_license_allowed = true;
            }
        }

        $context = '';
        foreach ($sources as $pt => $cfg) {
            if (empty($cfg['enabled'])) continue;
            if ($pt === 'product' && class_exists('WooCommerce') && $useWoo) {
                // Avoid duplicate: product is handled by Woo source when enabled
                continue;
            }
            
            // License restrictions: Skip custom post types for Basic/No license
            if (!$content_license_allowed && !in_array($pt, $allowed_basic_types, true)) {
                continue;
            }
            $label = strtoupper($pt);

            // Build query args
            $args = [
                'post_type'      => $pt,
                'post_status'    => 'publish',
                'posts_per_page' => max(1, absint($cfg['max_items'] ?? 5)),
                'orderby'        => in_array(($cfg['orderby'] ?? 'date'), ['date','title'], true) ? $cfg['orderby'] : 'date',
                'order'          => (strtoupper($cfg['order'] ?? 'DESC') === 'ASC') ? 'ASC' : 'DESC',
                's'              => sanitize_text_field($userMsg),
                'fields'         => 'ids',
            ];
            // tax filters - Only for Business+ licenses
            if ($content_license_allowed && !empty($cfg['tax']) && is_array($cfg['tax'])) {
                $tax_query = ['relation' => 'AND'];
                foreach ($cfg['tax'] as $tax => $terms) {
                    if (empty($terms)) continue;
                    $tax_query[] = [
                        'taxonomy' => sanitize_key($tax),
                        'field'    => 'slug',
                        'terms'    => array_map('sanitize_title', $terms),
                    ];
                }
                if (count($tax_query) > 1) $args['tax_query'] = $tax_query;
            }
            $args = apply_filters('aipc_cpt_query_args', $args, $cfg, $userMsg);

            // Cache per PT + query chunk
            $cache_key = 'aipc_cpt_ctx_' . md5($pt . '|' . maybe_serialize($cfg) . '|' . wp_strip_all_tags((string)$userMsg));
            $ids = get_transient($cache_key);
            if ($ids === false) {
                $ids = get_posts($args);
                set_transient($cache_key, $ids, 5 * MINUTE_IN_SECONDS);
            }
            if (empty($ids)) continue;

            $fields  = isset($cfg['fields']) && is_array($cfg['fields']) ? $cfg['fields'] : ['title','excerpt','link'];
            // License restrictions: Disable advanced features for Basic/No license
            $meta_keys = [];
            $template = '';
            if ($content_license_allowed) {
                $meta_keys = isset($cfg['meta_keys']) ? (array)$cfg['meta_keys'] : [];
                $template = isset($cfg['template']) && is_string($cfg['template']) && $cfg['template'] !== '' ? $cfg['template'] : '';
            }
            $maxChars= max(50, absint($cfg['max_chars'] ?? 400));

            $context .= $label . ":\n";
            foreach ($ids as $pid) {
                $title   = get_the_title($pid);
                $excerpt = wp_strip_all_tags(get_the_excerpt($pid));
                $content = wp_strip_all_tags(get_post_field('post_content', $pid, 'raw'));
                $link    = get_permalink($pid);

                // Collect meta values
                $meta_values = [];
                if (!empty($meta_keys)) {
                    foreach ($meta_keys as $mk) {
                        $val = get_post_meta($pid, $mk, true);
                        if ($val !== '') {
                            $meta_values[strtolower($mk)] = wp_strip_all_tags((string)$val);
                        }
                    }
                }

                if ($template) {
                    $vars = [
                        'title' => $title,
                        'excerpt' => $excerpt,
                        'content' => $content,
                        'link' => $link,
                    ];
                    $item = $this->render_cpt_template($template, $vars, $meta_values, $maxChars);
                } else {
                    $item  = '- ' . $title . "\n";
                    if (in_array('excerpt', $fields, true) && $excerpt) {
                        $item .= '  Samenvatting: ' . mb_substr($excerpt, 0, $maxChars) . "...\n";
                    }
                    if (in_array('content', $fields, true) && $content) {
                        $item .= '  Content: ' . mb_substr($content, 0, $maxChars) . "...\n";
                    }
                    // Meta keys
                    if (!empty($meta_values)) {
                        foreach ($meta_values as $mk => $vv) {
                            $item .= '  ' . ucfirst($mk) . ': ' . $vv . "\n";
                        }
                    }
                    if (in_array('link', $fields, true) && $link) {
                        $item .= '  URL: ' . $link . "\n";
                    }
                }

                $item = apply_filters('aipc_cpt_context_item', $item, $pid, $cfg);
                $context .= $item . "\n";
            }
            $context .= "\n";
        }

        return $context;
    }

    private function extract_comparison_titles($message) {
        $m = strtolower($message);
        // Typical Dutch phrasing: "verschil tussen X en Y"; also tolerate extra punctuation/whitespace
        if (preg_match('/verschil\s+tussen\s+(.+?)\s+en\s+(.+?)(\?|\.|$)/iu', $message, $mm)) {
            $left = trim($mm[1]);
            $right = trim($mm[2]);
            // Clean trailing punctuation
            $left = trim(preg_replace('/[\p{P}\p{S}]+$/u', '', $left));
            $right = trim(preg_replace('/[\p{P}\p{S}]+$/u', '', $right));
            // Normalize common typos like "vitamince" -> "vitamine"
            $left = preg_replace('/\bvitamince\b/iu', 'vitamine c', $left);
            $right = preg_replace('/\bvitamince\b/iu', 'vitamine c', $right);
            return array_values(array_filter([$left, $right]));
        }
        return [];
    }
    
    private function is_general_product_question($message) {
        $message_lower = strtolower($message);
        
        // Exclude ingredient info questions from being treated as general product questions
        // But allow product info questions like "heb je informatie over product X"
        if ((strpos($message_lower, 'info') !== false || strpos($message_lower, 'informatie') !== false) &&
            (strpos($message_lower, 'ingredient') !== false || strpos($message_lower, 'ingrediënt') !== false)) {
            return false;
        }
        
        // Also exclude standalone ingredient questions
        if (strpos($message_lower, 'ingredient') !== false || strpos($message_lower, 'ingrediënt') !== false) {
            return false;
        }
        
        $general_questions = [
            'welke producten',
            'welk product',
            'wat voor producten', 
            'wat voor product',
            'alle producten',
            'producten lijst',
            'product lijst',
            'heb je producten',
            'welke producten heb je',
            'welk product heb je',
            'producten voor',        // NEW: "producten voor diabeet"
            'product voor',          // NEW: "product voor hart"
            'producten tegen',       // NEW: "producten tegen acne"
            'product tegen',         // NEW: "product tegen rimpels"
            'producten bij',         // NEW: "producten bij droge huid"
            'product bij',           // NEW: "product bij puistjes"
            'wat kost',
            'prijs van',
            'hoeveel kost'
        ];
        
        foreach ($general_questions as $question) {
            if (strpos($message_lower, $question) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function extract_keywords($message) {
        // Simple keyword extraction with punctuation cleanup
        $lower = strtolower($message);
        $words = explode(' ', $lower);
        $cleaned = [];
        $stop = [
            'de','het','een','en','of','voor','met','op','aan','in','uit','over','onder','tegen','naar','bij','van','tot','als','dan','maar','ook','dus','toch','al','nog','wel','niet','geen','heb','hebt','heeft','hebben','ben','bent','is','zijn','was','waren','wordt','worden','kan','kunnen','wil','wilt','wil','willen','mag','mogen','u','je','jij','we','wij','ze','zij','ik','mij','me','jou','jouw','jullie','product','producten','vraag','vragen','ingredient','ingrediënt','info','informatie','weet','weten','vertel','vertellen','welk','welke','wat','hoe','waar','wanneer','wie','waarom','helpt','helpen','middel','middelen','behandeling','behandelen','goed','goede','beste','raad','raden','advies','aanbevelen'
        ];
        foreach ($words as $word) {
            // Strip punctuation/symbols from both ends, keep letters/numbers
            $w = preg_replace('/^[\p{P}\p{S}]+|[\p{P}\p{S}]+$/u', '', $word);
            if ($w !== null) {
                $w = trim($w);
            }
            if ($w !== '' && strlen($w) > 2 && !in_array($w, $stop, true)) {
                $cleaned[] = $w;
            }
        }
        return array_values(array_unique($cleaned));
    }
    
    private function call_openai_api($messages, $conversation_id = null) {
        $api_monitor = AIPC_API_Monitor::getInstance();
        $start_time = microtime(true);
        
        $url = rtrim($this->api_base, '/') . '/chat/completions';
        
        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $this->max_tokens,
            'temperature' => $this->temperature,
            'stream' => false
        ];
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        ];
        if ($this->provider === 'openrouter') {
            $headers['HTTP-Referer'] = home_url('/');
            $headers['X-Title'] = get_bloginfo('name');
        }
        
        // Simple retry with exponential backoff for transient errors
        $attempts = 0;
        $max_attempts = 3;
        $lastErr = null;
        
        while ($attempts < $max_attempts) {
            $attempts++;
            $attempt_start = microtime(true);
            
            $response = wp_remote_post($url, [
                'headers' => $headers,
                'body' => json_encode($data),
                'timeout' => 30
            ]);
            
            $response_time = round((microtime(true) - $start_time) * 1000, 2);
            
            if (is_wp_error($response)) {
                $lastErr = $response->get_error_message();
                
                // Log connection error
                $api_monitor->log_api_request([
                    'conversation_id' => $conversation_id,
                    'provider' => $this->provider,
                    'model' => $this->model,
                    'status' => 'error',
                    'error_message' => 'Connection error: ' . $lastErr,
                    'response_time_ms' => $response_time,
                    'retry_attempt' => $attempts - 1,
                    'request_type' => 'ai_api'
                ]);
                
                // Don't retry on network configuration issues
                if (strpos($lastErr, 'timeout') === false && strpos($lastErr, '5') === false) {
                    break;
                }
                
                // Exponential backoff for retries
                if ($attempts < $max_attempts) {
                    sleep(min(pow(2, $attempts - 1), 5)); // Max 5 seconds
                }
                continue;
            }
            
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $json = json_decode($body, true);
            
            if ($code === 200 && isset($json['choices'][0]['message']['content'])) {
                $usage = isset($json['usage']) && is_array($json['usage']) ? $json['usage'] : [];
                $request_id = wp_remote_retrieve_header($response, 'x-request-id');
                
                // Calculate cost estimate
                $prompt_tokens = intval($usage['prompt_tokens'] ?? 0);
                $completion_tokens = intval($usage['completion_tokens'] ?? 0);
                $total_tokens = intval($usage['total_tokens'] ?? ($prompt_tokens + $completion_tokens));
                
                $input_rate = floatval(get_option('aipc_token_input_rate_per_1k', 0));
                $output_rate = floatval(get_option('aipc_token_output_rate_per_1k', 0));
                $cost = (($prompt_tokens * $input_rate) + ($completion_tokens * $output_rate)) / 1000.0;
                
                // Log successful request
                $api_monitor->log_api_request([
                    'conversation_id' => $conversation_id,
                    'provider' => $this->provider,
                    'model' => $this->model,
                    'status' => 'success',
                    'http_status' => $code,
                    'response_time_ms' => $response_time,
                    'prompt_tokens' => $prompt_tokens,
                    'completion_tokens' => $completion_tokens,
                    'total_tokens' => $total_tokens,
                    'cost_estimate' => $cost,
                    'retry_attempt' => $attempts - 1,
                    'request_type' => 'ai_api'
                ]);
                
                return [
                    'success' => true,
                    'data' => [ 'content' => $json['choices'][0]['message']['content'] ],
                    'usage' => $usage,
                    'request_id' => $request_id ? (string)$request_id : null,
                    'http_code' => $code,
                ];
            }
            
            // Determine error status
            $error_status = 'error';
            if ($code === 429) $error_status = 'rate_limit';
            elseif ($code === 408 || strpos($body, 'timeout') !== false) $error_status = 'timeout';
            elseif (in_array($code, [402, 403]) && strpos($body, 'quota') !== false) $error_status = 'quota_exceeded';
            
            // Map provider errors to clearer messages
            $friendly = $this->format_provider_error_message($code, $json);
            
            // Log API error
            $api_monitor->log_api_request([
                'conversation_id' => $conversation_id,
                'provider' => $this->provider,
                'model' => $this->model,
                'status' => $error_status,
                'http_status' => $code,
                'error_code' => isset($json['error']['code']) ? $json['error']['code'] : null,
                'error_message' => $friendly,
                'response_time_ms' => $response_time,
                'retry_attempt' => $attempts - 1,
                'request_type' => 'ai_api'
            ]);
            
            // Retry with exponential backoff on specific errors
            if (in_array($code, [429, 500, 502, 503, 504], true) && $attempts < $max_attempts) {
                // Respect rate limit headers
                $retryAfter = intval(wp_remote_retrieve_header($response, 'retry-after'));
                if ($retryAfter > 0 && $retryAfter <= 10) {
                    sleep($retryAfter);
                } else {
                    // Exponential backoff: 1s, 2s, 4s
                    sleep(min(pow(2, $attempts - 1), 5));
                }
                continue;
            }
            
            return [ 'success' => false, 'message' => $friendly ];
        }
        
        // Final error after all retries exhausted
        $final_response_time = round((microtime(true) - $start_time) * 1000, 2);
        $api_monitor->log_api_request([
            'conversation_id' => $conversation_id,
            'provider' => $this->provider,
            'model' => $this->model,
            'status' => 'error',
            'error_message' => $lastErr ? 'All retries failed: ' . $lastErr : 'All retries failed: Unknown error',
            'response_time_ms' => $final_response_time,
            'retry_attempt' => $attempts - 1,
            'request_type' => 'ai_api'
        ]);
        
        return [
            'success' => false,
            'message' => $lastErr ? sprintf(__('Verbindingsfout met provider: %s', 'ai-product-chatbot'), $lastErr) : __('Onbekende providerfout.', 'ai-product-chatbot')
        ];
    }

    private function log_token_usage($conversation_id, array $usage) {
        global $wpdb;
        $table = $wpdb->prefix . 'aipc_token_usage';
        $provider = get_option('aipc_api_provider', 'openai');
        $model = get_option('aipc_openai_model', '');
        $in_rate = floatval(get_option('aipc_token_input_rate_per_1k', 0));
        $out_rate = floatval(get_option('aipc_token_output_rate_per_1k', 0));
        $prompt = intval($usage['prompt_tokens'] ?? 0);
        $completion = intval($usage['completion_tokens'] ?? 0);
        $total = intval($usage['total_tokens'] ?? ($prompt + $completion));
        $cost = (($prompt * $in_rate) + ($completion * $out_rate)) / 1000.0;
        $wpdb->insert($table, [
            'conversation_id' => $conversation_id,
            'provider' => $provider,
            'model' => $model,
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total,
            'input_rate_per_1k' => $in_rate,
            'output_rate_per_1k' => $out_rate,
            'cost_estimate' => $cost,
            'created_at' => current_time('mysql')
        ]);
    }

    private function format_provider_error_message($code, $json) {
        $providerName = ($this->provider === 'openrouter') ? 'OpenRouter' : 'OpenAI';
        $model = is_string($this->model) ? $this->model : '';
        $detail = '';
        if (is_array($json) && isset($json['error'])) {
            $detail = isset($json['error']['message']) ? (string)$json['error']['message'] : '';
        }
        switch (intval($code)) {
            case 0:
                return __('Geen netwerkverbinding met de provider mogelijk.', 'ai-product-chatbot');
            case 400:
                return $detail ?: __('Ongeldig verzoek naar de provider (controleer invoer/parameters).', 'ai-product-chatbot');
            case 401:
                return __('Ongeldige of ontbrekende API key. Controleer je sleutel en probeer opnieuw.', 'ai-product-chatbot');
            case 403:
                return __('Toegang geweigerd door de provider. Controleer rechten en modeltoegang.', 'ai-product-chatbot');
            case 404:
                return sprintf(__('Model niet gevonden of niet beschikbaar: %s', 'ai-product-chatbot'), $model ?: 'onbekend');
            case 408:
                return __('De provider deed er te lang over om te reageren (timeout). Probeer het opnieuw.', 'ai-product-chatbot');
            case 409:
                return __('Verzoek in conflict. Probeer het nogmaals.', 'ai-product-chatbot');
            case 429:
                return __('Te veel verzoeken (rate limit). Even wachten en opnieuw proberen.', 'ai-product-chatbot');
            case 500:
            case 502:
            case 503:
            case 504:
                return sprintf(__('Tijdelijke storing bij %s. Probeer het zo dadelijk opnieuw.', 'ai-product-chatbot'), $providerName);
            default:
                if ($detail) { return $detail; }
                return sprintf(__('Providerfout (%d). Probeer het opnieuw.', 'ai-product-chatbot'), intval($code));
        }
    }
    
    private function get_conversation_history($conversation_id) {
        if (empty($conversation_id)) {
            return [];
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_conversations';
        
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT role, content FROM $table_name WHERE conversation_id = %s ORDER BY created_at ASC LIMIT 20",
            $conversation_id
        ));
        
        return array_map(function($msg) {
            return ['role' => $msg->role, 'content' => $msg->content];
        }, $messages);
    }
    
    private function get_conversation_product_context($message, $conversation_history) {
        $message_lower = strtolower($message);
        
        // Check if message contains reference words indicating it refers to previous context
        $has_reference_words = (strpos($message_lower, 'daar') !== false || 
                               strpos($message_lower, 'dit') !== false || 
                               strpos($message_lower, 'het') !== false ||
                               strpos($message_lower, 'ervan') !== false);
        
        if (!$has_reference_words || empty($conversation_history)) {
            return '';
        }
        
        // Look for products mentioned in recent conversation
        $recent_messages = array_slice($conversation_history, -5);
        $mentioned_products = [];
        
        foreach ($recent_messages as $msg) {
            // Extract product names from conversation
            $product_patterns = [
                '/\b([A-Z][a-z]+ ?[A-Z]?[a-z]*(?:\s+[a-z]+)*(?:gel|cream|oil|lotion|serum|shampoo|showergel|bodymilk))\b/i',
                '/\b(Energy\s+\w+)\b/i',
                '/\b(Family\s+\w+)\b/i',
                '/\b(Sensitive\s+\w+)\b/i'
            ];
            
            foreach ($product_patterns as $pattern) {
                if (preg_match_all($pattern, $msg['content'], $matches)) {
                    foreach ($matches[1] as $match) {
                        $clean_match = trim($match);
                        if (strlen($clean_match) > 5) {
                            $mentioned_products[] = $clean_match;
                        }
                    }
                }
            }
        }
        
        if (empty($mentioned_products)) {
            return '';
        }
        
        // Get product data for the mentioned products
        $context = "RECENT GESPREKSCONTEXT - Recent genoemde producten:\n";
        $unique_products = array_unique($mentioned_products);
        
        foreach ($unique_products as $product_name) {
            // Search for this specific product
            $products = $this->search_woocommerce_products_scored($product_name, [$product_name]);
            if (!empty($products)) {
                $product = $products[0]; // Take the best match
                $context .= "- " . $product['name'] . ": " . $product['description'] . "\n";
                if (!empty($product['ingredients'])) {
                    $context .= "  Ingrediënten: " . implode(', ', $product['ingredients']) . "\n";
                }
                $context .= "\n";
            }
        }
        
        return $context;
    }
    
    private function store_conversation($conversation_id, $user_message, $ai_response) {
        try {
            // Valideer conversation_id
            if (empty($conversation_id)) {
                $conversation_id = wp_generate_uuid4();
            }
            
            // Controleer of opslag is uitgeschakeld
            if (!get_option('aipc_store_conversations', true)) {
                // Storage disabled by setting: return passthrough conversation id
                return $conversation_id;
            }
            
            // Valideer berichten
            if (!is_string($user_message)) {
                $user_message = '';
            }
            
            if (!is_string($ai_response)) {
                $ai_response = '';
            }
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'aipc_conversations';
            
            // Controleer of de tabel bestaat
            $exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name);
            if (!$exists) {
                return $conversation_id;
            }
            
            // Suppress DB error display during AJAX
            $prev = $wpdb->suppress_errors(true);
            
            // Store user message met foutafhandeling
            $user_result = $wpdb->insert($table_name, [
                'conversation_id' => $conversation_id,
                'role' => 'user',
                'content' => $user_message,
                'created_at' => current_time('mysql')
            ]);
            
            
            // Store AI response met foutafhandeling
            $ai_result = $wpdb->insert($table_name, [
                'conversation_id' => $conversation_id,
                'role' => 'assistant',
                'content' => $ai_response,
                'created_at' => current_time('mysql')
            ]);
            
            
            // Restore error handling
            $wpdb->suppress_errors($prev);
        } catch (Exception $e) {
            error_log('AIPC Error in store_conversation: ' . $e->getMessage());
        }
        
        return $conversation_id;
    }
    
    private function get_fallback_response($message, $product_context = '') {
        $message_lower = strtolower($message);
        
        // PRIORITY: Check FAQ first - if we have an FAQ answer, return it without product prefix
        $faq_answer = $this->get_faq_answer($message);
        if (!empty($faq_answer)) {
            return $faq_answer;
        }
        
        // Check if we have product context
        $has_products = !empty($product_context);

        // Product count intent
        if ($this->is_product_count_question($message_lower)) {
            $count = 0;
            $post_counts = wp_count_posts('product');
            if ($post_counts && isset($post_counts->publish)) {
                $count = intval($post_counts->publish);
            }
            if ($count > 0) {
                return sprintf('We hebben momenteel %d producten beschikbaar in de webshop.', $count);
            }
            return 'Ik kan op dit moment geen exact aantal producten ophalen.';
        }
        
        // Smart defaults: tips/advies als er expliciet om gevraagd wordt
        if (strpos($message_lower, 'tips') !== false || preg_match('/\b(3|drie)\b.+tips/u', $message_lower)) {
            return $this->get_universal_tips($this->is_current_locale_english());
        }

        // Simple keyword-based responses
        if (strpos($message_lower, 'hallo') !== false || strpos($message_lower, 'hi') !== false || strpos($message_lower, 'hey') !== false) {
            $is_en = $this->is_current_locale_english();
            $gwp = trim(get_option('aipc_greet_with_products', ''));
            $gwo = trim(get_option('aipc_greet_without_products', ''));
            if ($gwp && function_exists('pll_translate_string')) { $gwp = pll_translate_string($gwp, 'ai-product-chatbot'); }
            elseif ($gwp && has_filter('wpml_translate_single_string')) { $gwp = apply_filters('wpml_translate_single_string', $gwp, 'ai-product-chatbot', 'aipc_greet_with_products'); }
            if ($gwo && function_exists('pll_translate_string')) { $gwo = pll_translate_string($gwo, 'ai-product-chatbot'); }
            elseif ($gwo && has_filter('wpml_translate_single_string')) { $gwo = apply_filters('wpml_translate_single_string', $gwo, 'ai-product-chatbot', 'aipc_greet_without_products'); }
            if ($has_products) {
                if ($gwp) return $gwp;
                return $is_en
                    ? "Hello! I'm your AI product assistant. I can help with product recommendations, product information and personal advice. I have access to our product catalog. How can I help you?"
                    : "Hallo! Ik ben je AI product assistant. Ik kan je helpen met productaanbevelingen, productinformatie en persoonlijke adviezen. Ik heb toegang tot onze productdatabase. Wat kan ik voor je doen?";
            }
            if ($gwo) return $gwo;
            return $is_en
                ? "Hello! I'm your AI product assistant. I can help with product recommendations, product information and personal advice. How can I help you today?"
                : "Hallo! Ik ben je AI product assistant. Ik kan je helpen met productaanbevelingen, productinformatie en persoonlijke adviezen. Wat kan ik voor je doen?";
        }
        
        
        if (strpos($message_lower, 'ingrediënt') !== false || strpos($message_lower, 'ingredient') !== false || strpos($message_lower, 'component') !== false || strpos($message_lower, 'specificatie') !== false) {
            if ($has_products) {
                return "Ik kan je helpen met uitleg over verschillende productcomponenten en eigenschappen! " . $this->get_ingredient_info($product_context);
            }
            return "Ik kan je helpen met uitleg over verschillende productcomponenten en eigenschappen! Welk specifiek onderdeel wil je meer over weten?";
        }
        
        
        // Check for ingredient-specific questions with database-first approach
        if ($this->is_ingredient_question($message_lower)) {
            
            // Use the same logic that works for short questions like "Aloë vera?"
            if ($has_products) {
                $ingredient_info = $this->get_specific_ingredient_info($message, $product_context);
                if (!empty($ingredient_info)) {
                    return $ingredient_info;
                }
            }
            
            // If not found in products, try to extract ingredient name for database lookup
            $ingredient_name = $this->extract_ingredient_from_message($message_lower);
            if (!$ingredient_name && $has_products) {
                // Fallback: try to find ingredient in product descriptions
                $ingredient_name = $this->find_ingredient_in_product_context($message_lower, $product_context);
            }
            
            if ($ingredient_name) {
                // Check database for ingredient benefits
                $db_info = $this->get_ingredient_benefits($ingredient_name);
                if (!empty($db_info)) {
                    // Found in database - combine with matching products
                    $response = "Over " . ucfirst($ingredient_name) . ":\n" . $db_info;
                    if ($has_products) {
                        $matching_products = $this->get_products_containing_ingredient($ingredient_name, $product_context);
                        if (!empty($matching_products)) {
                            $response .= "\n\nProducten met dit ingrediënt:\n" . implode("\n", $matching_products);
                        }
                    }
                    return $response;
                }
                
                // Check if AI fallback is enabled
                $allow_ai_ingredient_fallback = get_option('aipc_allow_ai_ingredient_info', true);
                if ($allow_ai_ingredient_fallback) {
                    // Return null to let AI API handle with general knowledge
                    return null; // Signal to use AI API
                } else {
                    // Database only - no AI fallback
                    return "Ik heb geen specifieke informatie over " . $ingredient_name . " in mijn database. Kan ik je op een andere manier helpen?";
                }
            }
        }
        
        // Check for specific product names (only if not an ingredient question)
        if ($has_products && !$this->is_ingredient_question($message_lower)) {
            $product_info = $this->get_specific_product_info($message, $product_context);
            if (!empty($product_info)) {
                return $product_info;
            }
        }
        
        if (strpos($message_lower, 'product') !== false || strpos($message_lower, 'producten') !== false) {
            $is_en = $this->is_current_locale_english();
            if ($has_products) {
                return $is_en
                    ? ("I can help with product recommendations! " . $this->get_available_products($product_context))
                    : ("Ik kan je helpen met productaanbevelingen! " . $this->get_available_products($product_context));
            }
            return $is_en
                ? "I can help with product recommendations! Tell me more about what you're looking for, then I can suggest the best products."
                : "Ik kan je helpen met productaanbevelingen! Vertel me meer over wat je zoekt, dan kan ik je de beste producten aanraden.";
        }
        
        if (strpos($message_lower, 'verschil') !== false || strpos($message_lower, 'vergelijken') !== false) {
            return "Ik kan je helpen producten te vergelijken! Welke producten wil je met elkaar vergelijken?";
        }
        
        // Simple test response (but not for quiz/product test terms, and only if skin test is disabled)
        // Use same logic as above for consistency
        $has_license_here = false;
        if (class_exists('AIPC_License')) {
            $lic = \AIPC_License::getInstance();
            $has_license_here = ($lic->is_active() && $lic->has_feature('custom_skin_test'));
        }
        $default_enabled_here = $has_license_here ? true : false;
        $skin_test_enabled = get_option('aipc_enable_skin_test', $default_enabled_here);
        if (!$skin_test_enabled && preg_match('/^\s*test\s*$/i', $message)) {
            // Only match standalone 'test' word to avoid interfering with real conversations
            $is_en = $this->is_current_locale_english();
            return $is_en 
                ? "I'm not sure what you're looking for. Could you be more specific? I can help with product recommendations, product information, or shopping advice!"
                : "Ik begrijp niet helemaal wat je bedoelt. Kun je je vraag wat specifieker stellen? Ik kan helpen met productaanbevelingen, productinformatie, of algemeen winkeladvies!";
        }
        
        if (strpos($message_lower, 'help') !== false || strpos($message_lower, 'hulp') !== false) {
            return $this->get_universal_help($this->is_current_locale_english());
        }
        
        if (strpos($message_lower, 'advies') !== false || strpos($message_lower, 'adviezen') !== false) {
            return "Ik geef graag persoonlijk advies! Vertel me meer over wat je zoekt of waar je vragen over hebt, dan kan ik je de beste producten aanraden.";
        }
        
        // Check if message contains product names from database
        if ($has_products) {
            $product_info = $this->get_specific_product_info($message, $product_context);
            if (!empty($product_info)) {
                return $product_info;
            }
            
            // Check if message contains specific ingredients
            $ingredient_info = $this->get_specific_ingredient_info($message, $product_context);
            if (!empty($ingredient_info)) {
                return $ingredient_info;
            }
        }
        
        // Default response
        $fallback = get_option('aipc_fallback_message', '');
        if (!$fallback) {
            $fallback = "Bedankt voor je bericht! Ik ben hier om je te helpen met productaanbevelingen, productinformatie en persoonlijke adviezen. Kun je me meer vertellen over wat je zoekt?";
        }
        // Translate dynamic fallback via Polylang/WPML if available
        if (function_exists('pll_translate_string')) {
            $fallback = pll_translate_string($fallback, 'ai-product-chatbot');
        } elseif (has_filter('wpml_translate_single_string')) {
            // Use the same key as registration to avoid duplicate entries
            $fallback = apply_filters('wpml_translate_single_string', $fallback, 'ai-product-chatbot', 'aipc_fallback_message');
        }
        return $fallback;
    }
    
    private function get_universal_tips($is_english = false) {
        if ($is_english) {
            return "Here are 3 general shopping tips:\n\n" .
                "1) Compare prices and read reviews before making a purchase.\n" .
                "2) Consider the quality and durability of products, not just the price.\n" .
                "3) Think about how the product fits your needs and lifestyle.\n\n" .
                "Looking for specific product advice? Let me know what you need!";
        }
        return "Hier zijn 3 algemene shop tips:\n\n" .
            "1) Vergelijk prijzen en lees reviews voordat je een aankoop doet.\n" .
            "2) Overweeg de kwaliteit en duurzaamheid van producten, niet alleen de prijs.\n" .
            "3) Denk na over hoe het product past bij jouw behoeften en levensstijl.\n\n" .
            "Op zoek naar specifiek productadvies? Laat me weten wat je nodig hebt!";
    }
    
    private function get_universal_help($is_english = false) {
        if ($is_english) {
            return "I can help you with:\n• Product recommendations\n• Product information\n• Product comparisons\n• Shopping advice\n\nTell me what you're looking for!";
        }
        return "Ik kan je helpen met:\n• Productaanbevelingen\n• Productinformatie\n• Productvergelijkingen\n• Shop advies\n\nVertel me wat je zoekt!";
    }

    private function is_product_count_question($message_lower) {
        $patterns = [
            // Dutch
            'hoeveel producten',
            'aantal producten',
            'hoeveel items',
            'hoeveel artikelen',
            // English
            'how many products',
            'number of products',
            'how many items',
            'how many articles',
            'products do you sell',
            'how many do you sell'
        ];
        foreach ($patterns as $p) {
            if (strpos($message_lower, $p) !== false) return true;
        }
        return false;
    }
    
    private function is_ingredient_question($message_lower) {
        $ingredient_keywords = [
            'ingredi', 'component', 'bestandd', 'samenstelling', 'bevat',
            'zitten in', 'zit in', 'welke stoffen', 'wat zit er in'
        ];
        
        foreach ($ingredient_keywords as $keyword) {
            if (strpos($message_lower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function extract_ingredient_from_message($message_lower) {
        // Extract potential ingredient names from the message
        // Look for patterns like "over [ingredient]", "wat doet [ingredient]", etc.
        $patterns = [
            '/(?:over|about)\s+([\w\s]+?)(?:\?|$|\s+voor|\s+tegen|\s+bij)/i',
            '/(?:wat doet|what does)\s+([\w\s]+?)(?:\?|$|\s+voor|\s+tegen)/i',
            '/(?:voordelen van|benefits of)\s+([\w\s]+?)(?:\?|$)/i',
            '/(?:werking van|effects of)\s+([\w\s]+?)(?:\?|$)/i',
            '/(?:info.*over|information.*about)\s+([\w\s]+?)(?:\?|$)/i',
            '/(?:heb je info over|do you have info about)\s+([\w\s]+?)(?:\?|$)/i',
            '/(?:wat weet je over|what do you know about)\s+([\w\s]+?)(?:\?|$)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message_lower, $matches)) {
                $potential = trim($matches[1]);
                // Clean up common words but preserve compound ingredient names
                $potential = preg_replace('/\b(het|de|een|ingredient|ingrediënt|component)\b/i', '', $potential);
                $potential = trim(preg_replace('/\s+/', ' ', $potential));
                
                // Special handling for compound names (e.g., "aloe vera", "hyaluronic acid")
                if (strlen($potential) > 2) {
                    // Don't filter out short words if they're part of known compound ingredients
                    $compound_ingredients = [
                        'aloe vera', 'aloë vera', 'hyaluronic acid', 'lactic acid', 'glycolic acid', 
                        'salicylic acid', 'citric acid', 'tea tree', 'shea butter', 'cocoa butter',
                        'vitamin a', 'vitamin b', 'vitamin c', 'vitamin e', 'vitamine a', 'vitamine b',
                        'vitamine c', 'vitamine e'
                    ];
                    
                    // Check if it matches a known compound ingredient and normalize to standard spelling
                    foreach ($compound_ingredients as $compound) {
                        if (stripos($potential, $compound) !== false) {
                            // Always return the standard spelling without trema for consistent matching
                            if ($compound === 'aloë vera') {
                                return 'aloe vera';
                            }
                            return $compound;
                        }
                    }
                    
                    return $potential;
                }
            }
        }
        
        return null;
    }
    
    private function find_ingredient_in_product_context($query, $product_context) {
        if (empty($product_context)) {
            return null;
        }
        
        $lines = explode("\n", $product_context);
        $query_words = array_filter(explode(' ', $query), function($word) {
            return strlen(trim($word)) > 3; // Skip short words
        });
        
        foreach ($lines as $line) {
            if (strpos($line, 'Ingrediënten:') !== false) {
                $ingredient_line = trim(str_replace('Ingrediënten:', '', $line));
                $ingredients = explode(', ', $ingredient_line);
                
                foreach ($ingredients as $ingredient) {
                    $ingredient = trim($ingredient);
                    foreach ($query_words as $word) {
                        if (stripos($ingredient, trim($word)) !== false) {
                            return $ingredient;
                        }
                    }
                }
            }
        }
        
        return null;
    }
    
    private function get_products_containing_ingredient($ingredient_name, $product_context) {
        if (empty($product_context)) {
            return [];
        }
        
        $lines = explode("\n", $product_context);
        $products = [];
        $current_product = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Check if this is a product name line
            if (strpos($line, '- ') === 0) {
                $product_name = trim(substr($line, 2));
                if (strpos($product_name, ':') !== false) {
                    $product_name = trim(explode(':', $product_name)[0]);
                }
                $current_product = $product_name;
            }
            
            // Check if this product contains the ingredient
            if ($current_product && strpos($line, 'Ingrediënten:') !== false) {
                $ingredient_line = strtolower(trim(str_replace('Ingrediënten:', '', $line)));
                if (strpos($ingredient_line, strtolower($ingredient_name)) !== false) {
                    $products[] = "- " . $current_product;
                }
            }
        }
        
        return $products;
    }
    
    private function get_combined_ingredient_and_product_info($message, $product_context) {
        if (empty($product_context)) {
            return '';
        }
        
        $message_lower = strtolower($message);
        $lines = explode("\n", $product_context);
        
        // Extract all ingredients and their products
        $ingredients = [];
        $current_product = null;
        $product_urls = [];
        $product_prices = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Check if this is a product name line
            if (strpos($line, '- ') === 0) {
                $product_name = trim(substr($line, 2));
                if (strpos($product_name, ':') !== false) {
                    $product_name = trim(explode(':', $product_name)[0]);
                }
                if (!empty($product_name)) {
                    $current_product = $product_name;
                }
            }
            
            // Collect product URLs and prices
            if ($current_product) {
                if (strpos($line, 'URL:') === 0) {
                    $product_urls[$current_product] = trim(substr($line, 4));
                } elseif (strpos($line, 'Prijs:') === 0) {
                    $product_prices[$current_product] = trim(substr($line, 6));
                }
            }
            
            // Check if this is an ingredients line
            if ($current_product && strpos($line, 'Ingrediënten:') !== false) {
                $ingredient_line = trim(str_replace('Ingrediënten:', '', $line));
                if (!empty($ingredient_line)) {
                    $ingredient_list = explode(', ', $ingredient_line);
                    foreach ($ingredient_list as $ingredient) {
                        $ingredient = trim($ingredient);
                        if (!empty($ingredient)) {
                            $ingredient_lower = strtolower($ingredient);
                            if (!isset($ingredients[$ingredient_lower])) {
                                $ingredients[$ingredient_lower] = [
                                    'name' => $ingredient,
                                    'products' => []
                                ];
                            }
                            $ingredients[$ingredient_lower]['products'][] = $current_product;
                        }
                    }
                }
            }
        }
        
        // Find matching ingredient
        $found_ingredient = null;
        $ingredient_name = '';
        foreach ($ingredients as $ingredient_key => $ingredient_info) {
            if (strpos($message_lower, $ingredient_key) !== false) {
                $found_ingredient = $ingredient_info;
                $ingredient_name = $ingredient_info['name'];
                break;
            }
        }
        
        // DISABLED: Hardcoded ingredient variations removed for flexibility
        // Users should define their own ingredient names and variations in the admin panel
        
        if (!$ingredient_name) {
            return '';
        }
        
        // Start building response with ingredient benefits
        $response = "Over " . $ingredient_name . ":\n\n";
        
        // Add ingredient benefits
        $benefits = $this->get_ingredient_benefits($ingredient_name);
        if (!empty($benefits)) {
            $response .= $benefits . "\n\n";
        } else {
            $response .= "Dit ingrediënt wordt gebruikt in cosmetische producten om specifieke huidvoordelen te bieden.\n\n";
        }
        
        // Add matching products if found
        if ($found_ingredient && !empty($found_ingredient['products'])) {
            $response .= "Van de producten in onze catalogus bevatten de volgende " . $ingredient_name . ":\n";
            
            $unique_products = array_unique($found_ingredient['products']);
            foreach ($unique_products as $product) {
                $response .= "- " . $product;
                
                // Add price if available
                if (isset($product_prices[$product]) && !empty($product_prices[$product])) {
                    $response .= " (" . $product_prices[$product] . ")";
                }
                
                // Add URL if available
                if (isset($product_urls[$product]) && !empty($product_urls[$product])) {
                    $response .= ": " . $product_urls[$product];
                }
                
                $response .= "\n";
            }
            
            // Add stock status note
            $response .= "\nHelaas zijn deze producten momenteel niet op voorraad.\n";
            $response .= "\nVoor persoonlijk advies over producten met " . $ingredient_name . ", neem gerust contact met ons op via onze contactpagina.";
        
        } else {
            // Fallback: search for products that might contain this ingredient in their name or description
            $matching_products = $this->find_products_mentioning_ingredient($ingredient_name, $product_context);
            if (!empty($matching_products)) {
                $response .= "**Gerelateerde producten:**\n";
                foreach ($matching_products as $product) {
                    $response .= "• " . $product['name'];
                    if (!empty($product['price'])) {
                        $response .= " (" . $product['price'] . ")";
                    }
                    if (!empty($product['url'])) {
                        $response .= " - " . $product['url'];
                    }
                    $response .= "\n";
                }
            }
        }
        
        return $response;
    }
    
    private function find_products_mentioning_ingredient($ingredient_name, $product_context) {
        $lines = explode("\n", $product_context);
        $products = [];
        $current_product = null;
        $ingredient_lower = strtolower($ingredient_name);
        
        foreach ($lines as $line) {
            $line = trim($line);
            $line_lower = strtolower($line);
            
            // Check if this is a product name line
            if (strpos($line, '- ') === 0) {
                $product_name = trim(substr($line, 2));
                if (strpos($product_name, ':') !== false) {
                    $parts = explode(':', $product_name, 2);
                    $product_name = trim($parts[0]);
                    $description = trim($parts[1]);
                } else {
                    $description = '';
                }
                
                // Check if ingredient is mentioned in product name or description
                if (strpos(strtolower($product_name), $ingredient_lower) !== false || 
                    strpos(strtolower($description), $ingredient_lower) !== false) {
                    $current_product = [
                        'name' => $product_name,
                        'price' => '',
                        'url' => ''
                    ];
                    $products[] = $current_product;
                }
            }
            
            // Add price and URL to last found product
            if (!empty($products) && strpos($line, 'Prijs:') === 0) {
                $products[count($products)-1]['price'] = trim(substr($line, 6));
            } elseif (!empty($products) && strpos($line, 'URL:') === 0) {
                $products[count($products)-1]['url'] = trim(substr($line, 4));
            }
        }
        
        return $products;
    }
    
    private function get_product_recommendations($product_context, $skin_type) {
        if (empty($product_context)) {
            return "Voeg producten toe aan de database voor specifieke aanbevelingen.";
        }
        
        // Extract product names from context
        $lines = explode("\n", $product_context);
        $products = [];
        
        foreach ($lines as $line) {
            if (strpos($line, '- ') === 0) {
                $product_name = trim(substr($line, 2));
                if (strpos($product_name, ':') !== false) {
                    $product_name = trim(explode(':', $product_name)[0]);
                    $products[] = $product_name;
                }
            }
        }
        
        if (!empty($products)) {
            $product_list = implode(', ', array_slice($products, 0, 3));
            return "In onze collectie hebben we onder andere: " . $product_list . ". Voeg meer producten toe aan de database voor gedetailleerde aanbevelingen.";
        }
        
        return "Voeg producten toe aan de database voor specifieke aanbevelingen.";
    }
    
    private function get_ingredient_info($product_context) {
        if (empty($product_context)) {
            return "Voeg producten met componenten/eigenschappen toe aan de database voor gedetailleerde informatie.";
        }
        
        // Extract components/ingredients from context - improved parsing
        $lines = explode("\n", $product_context);
        $components = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'Ingrediënten:') !== false || strpos($line, 'Components:') !== false || strpos($line, 'Features:') !== false) {
                $component_line = trim(preg_replace('/(Ingrediënten|Components|Features):\s*/', '', $line));
                if (!empty($component_line)) {
                    $component_list = explode(', ', $component_line);
                    $components = array_merge($components, $component_list);
                }
            }
        }
        
        if (!empty($components)) {
            $unique_components = array_unique($components);
            $component_list = implode(', ', array_slice($unique_components, 0, 5));
            return "In onze producten gebruiken we componenten/eigenschappen zoals: " . $component_list . ". Vraag naar specifieke componenten voor meer informatie.";
        }
        
        // Fallback: check if we have any product context
        if (strpos($product_context, 'PRODUCTEN:') !== false) {
            return "We gebruiken verschillende componenten en eigenschappen in onze producten. Vraag naar specifieke onderdelen of producten voor meer informatie.";
        }
        
        return "Voeg producten met componenten/eigenschappen toe aan de database voor gedetailleerde informatie.";
    }
    
    private function get_available_products($product_context) {
        if (empty($product_context)) {
            return "Voeg producten toe aan de database om te zien wat we aanbieden.";
        }
        
        
        // Extract product entries (name, url, price) from context
        $lines = explode("\n", $product_context);
        $entries = [];
        $current_key = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // Product header line "- Name: Description"
            if (strpos($line, '- ') === 0) {
                $product_name = trim(substr($line, 2));
                if (strpos($product_name, ':') !== false) {
                    $product_name = trim(explode(':', $product_name, 2)[0]);
                }
                if ($product_name !== '') {
                    $current_key = strtolower($product_name);
                    if (!isset($entries[$current_key])) {
                        $entries[$current_key] = [
                            'name' => $product_name,
                            'url' => '',
                            'price' => ''
                        ];
                    }
                }
                continue;
            }
            if ($current_key) {
                if (strpos($line, 'URL:') === 0) {
                    $entries[$current_key]['url'] = trim(substr($line, 4));
                } elseif (strpos($line, 'Prijs:') === 0) {
                    $entries[$current_key]['price'] = trim(substr($line, 6));
                }
            }
        }
        
        $items = array_values($entries);
        
        if (!empty($items)) {
            $display = array_slice($items, 0, 10);
            $bullets = [];
            foreach ($display as $it) {
                $label = $it['name'];
                if ($it['price'] !== '') {
                    $label .= " (" . $it['price'] . ")";
                }
                if ($it['url'] !== '') {
                    // Add plain URL so frontend autolinker makes it clickable
                    $label .= ": " . $it['url'];
                }
                $bullets[] = "- " . $label;
            }
            $more = count($items) - count($display);
            $message = "Op dit moment zijn deze producten op voorraad:\n\n" . implode("\n", $bullets);
            if ($more > 0) {
                $message .= "\n\n... en nog " . $more . " andere producten.";
            }
            return $message;
        }
        
        // Fallback: try to extract any product names from the context
        if (strpos($product_context, 'PRODUCTEN:') !== false) {
            return "We hebben verschillende producten in onze collectie. Vertel me meer over je huidtype (droog, vet, gevoelig) voor specifieke aanbevelingen.";
        }
        
        return "Voeg producten toe aan de database om te zien wat we aanbieden.";
    }
    
    private function get_specific_product_info($message, $product_context) {
        if (empty($product_context)) {
            return '';
        }
        
        
        $message_lower = strtolower($message);
        $lines = explode("\n", $product_context);
        
        // Extract all product names and their info
        $products = [];
        $current_product = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Check if this is a product name line (format: "- ProductName: Description")
            if (strpos($line, '- ') === 0) {
                $product_name = trim(substr($line, 2));
                if (strpos($product_name, ':') !== false) {
                    $parts = explode(':', $product_name, 2);
                    $product_name = trim($parts[0]);
                    $description = trim($parts[1]);
                } else {
                    $description = '';
                }
                
                if (!empty($product_name)) {
                    $current_product = strtolower($product_name);
                    $products[$current_product] = [
                        'name' => $product_name,
                        'description' => $description,
                        'ingredients' => '',
                        'skin_types' => '',
                        'price' => '',
                        'stock' => '',
                        'url' => ''
                    ];
                }
            }
            
            // Check if this is product info
            if ($current_product && !empty($line)) {
                if (strpos($line, 'Ingrediënten:') !== false) {
                    $products[$current_product]['ingredients'] = trim(str_replace('Ingrediënten:', '', $line));
                } elseif (strpos($line, 'Geschikt voor:') !== false) {
                    $products[$current_product]['skin_types'] = trim(str_replace('Geschikt voor:', '', $line));
                } elseif (strpos($line, 'Prijs:') !== false) {
                    $products[$current_product]['price'] = trim(str_replace('Prijs:', '', $line));
                } elseif (strpos($line, 'Voorraad:') !== false) {
                    $products[$current_product]['stock'] = trim(str_replace('Voorraad:', '', $line));
                } elseif (strpos($line, 'URL:') !== false) {
                    $products[$current_product]['url'] = trim(str_replace('URL:', '', $line));
                } elseif (!strpos($line, '- ') && !strpos($line, 'PRODUCTEN:') && !strpos($line, 'INGREDIËNTEN:')) {
                    // This might be the description
                    if (empty($products[$current_product]['description'])) {
                        $products[$current_product]['description'] = $line;
                    }
                }
            }
        }
        
        // Check if message contains any product name
        foreach ($products as $product_key => $product_info) {
            if (strpos($message_lower, $product_key) !== false) {
                $response = "Over " . $product_info['name'] . ":\n";
                
                if (!empty($product_info['description'])) {
                    $response .= $product_info['description'] . "\n";
                }
                
                if (!empty($product_info['ingredients'])) {
                    $response .= "Ingrediënten: " . $product_info['ingredients'] . "\n";
                }
                
                if (!empty($product_info['skin_types'])) {
                    $response .= "Geschikt voor: " . $product_info['skin_types'] . "\n";
                }
                
                if (!empty($product_info['price'])) {
                    $response .= "Prijs: " . $product_info['price'] . "\n";
                }
                
                if (!empty($product_info['stock'])) {
                    $response .= "Voorraad: " . $product_info['stock'] . "\n";
                }
                
                // Add product URL if available
                if (!empty($product_info['url'])) {
                    $response .= "Bekijk dit product: <a href='" . esc_url($product_info['url']) . "' target='_blank'>" . $product_info['url'] . "</a>\n";
                }
                
                return $response;
            }
        }
        
        return '';
    }
    
    private function get_specific_ingredient_info($message, $product_context) {
        if (empty($product_context)) {
            return '';
        }
        
        $message_lower = strtolower($message);
        $lines = explode("\n", $product_context);
        
        // Extract all ingredients and their products
        $ingredients = [];
        $current_product = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Check if this is a product name line
            if (strpos($line, '- ') === 0) {
                $product_name = trim(substr($line, 2));
                if (strpos($product_name, ':') !== false) {
                    $product_name = trim(explode(':', $product_name)[0]);
                }
                if (!empty($product_name)) {
                    $current_product = $product_name;
                }
            }
            
            // Check if this is an ingredients line
            if ($current_product && strpos($line, 'Ingrediënten:') !== false) {
                $ingredient_line = trim(str_replace('Ingrediënten:', '', $line));
                if (!empty($ingredient_line)) {
                    $ingredient_list = explode(', ', $ingredient_line);
                    foreach ($ingredient_list as $ingredient) {
                        $ingredient = trim($ingredient);
                        if (!empty($ingredient)) {
                            $ingredient_lower = strtolower($ingredient);
                            if (!isset($ingredients[$ingredient_lower])) {
                                $ingredients[$ingredient_lower] = [
                                    'name' => $ingredient,
                                    'products' => []
                                ];
                            }
                            $ingredients[$ingredient_lower]['products'][] = $current_product;
                        }
                    }
                }
            }
        }
        
        // Check if message contains any ingredient name
        foreach ($ingredients as $ingredient_key => $ingredient_info) {
            if (strpos($message_lower, $ingredient_key) !== false) {
                $response = "Over " . $ingredient_info['name'] . ":\n";
                
                // Add general ingredient benefits based on common ingredients
                $benefits = $this->get_ingredient_benefits($ingredient_info['name']);
                if (!empty($benefits)) {
                    $response .= $benefits . "\n";
                } else {
                    // Fallback for unknown ingredients
                    $response .= "Dit ingrediënt wordt gebruikt in onze producten. ";
                }
                
                // Add products that contain this ingredient
                if (!empty($ingredient_info['products'])) {
                    $product_list = implode(', ', $ingredient_info['products']);
                    $response .= "Dit ingrediënt zit in onze producten: " . $product_list . "\n";
                }
                
                return $response;
            }
        }
        
        // DISABLED: Hardcoded ingredient variations removed for flexibility
        // Users should define their own ingredient names and variations in the admin panel
        
        return '';
    }
    
    private function get_ingredient_benefits($ingredient_name) {
        // DISABLED: Hardcoded ingredient knowledge removed to make plugin more flexible
        // Users should define their own ingredients and benefits in the admin panel
        // This prevents the plugin from being limited to skincare/beauty only
        
        // Only check database/admin-defined ingredients now
        global $wpdb;
        $ingredient_table = $wpdb->prefix . 'aipc_ingredients';
        
        // Check if ingredients table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $ingredient_table)) === $ingredient_table) {
            $ingredient = $wpdb->get_row($wpdb->prepare(
                "SELECT description, benefits FROM $ingredient_table WHERE LOWER(name) = %s AND status != 'deleted'",
                strtolower($ingredient_name)
            ));
            
            if ($ingredient) {
                // Prefer description over benefits for main explanation
                if (!empty($ingredient->description)) {
                    return $ingredient->description;
                }
                // Fallback to benefits if no description (for backwards compatibility)
                if (!empty($ingredient->benefits)) {
                    $benefits = json_decode($ingredient->benefits, true);
                    if (is_array($benefits) && !empty($benefits)) {
                        return implode('. ', $benefits) . '.';
                    }
                }
            }
        }
        
        return '';
    }

    // Enhanced search with relevance scoring
    private function search_woocommerce_products_scored($query, $keywords = []) {
        $results = $this->search_woocommerce_products($query);
        if (empty($results) || empty($keywords)) {
            return $results;
        }
        
        // Score and sort results by relevance
        $scored_results = [];
        foreach ($results as $product) {
            $score = $this->calculate_product_relevance_score($product, $keywords, $query);
            $scored_results[] = ['score' => $score, 'product' => $product];
        }
        
        // Sort by score (highest first)
        usort($scored_results, function($a, $b) {
            return $a['score'] < $b['score'] ? 1 : ($a['score'] > $b['score'] ? -1 : 0);
        });
        
        // Return only products, sorted by relevance
        return array_map(function($item) { return $item['product']; }, $scored_results);
    }
    
    // Calculate relevance score for a product
    private function calculate_product_relevance_score($product, $keywords, $query) {
        $score = 0.0;
        $name = strtolower($product['name']);
        $description = strtolower($product['description']);
        $query_lower = strtolower($query);
        
        // Check if this is a medical condition query
        $medical_conditions = ['voetschimmel', 'schimmel', 'eczeem', 'acne', 'psoriasis', 'huidprobleem', 'fungal', 'infectie'];
        $is_medical_query = false;
        foreach ($medical_conditions as $condition) {
            if (strpos($query_lower, $condition) !== false) {
                $is_medical_query = true;
                break;
            }
        }
        
        // 1. Exact phrase match in name (highest score)
        if (strpos($name, $query_lower) !== false) {
            $score += 1.0;
        }
        
        // 2. Exact phrase match in description (boost for medical queries)
        if (strpos($description, $query_lower) !== false) {
            $score += $is_medical_query ? 1.2 : 0.8; // Higher score for medical descriptions
        }
        
        // 3. Individual keyword matches in name
        foreach ($keywords as $keyword) {
            if (strpos($name, strtolower($keyword)) !== false) {
                $score += 0.6;
            }
        }
        
        // 4. Individual keyword matches in description (boost for medical)
        foreach ($keywords as $keyword) {
            if (strpos($description, strtolower($keyword)) !== false) {
                $score += $is_medical_query ? 0.7 : 0.4; // Higher score for medical keyword matches
            }
        }
        
        // 5. Medical condition specific scoring
        if ($is_medical_query) {
            // Boost products that mention treatment/care terms
            $treatment_terms = ['behandeling', 'bestrijding', 'verzorging', 'helpt tegen', 'geschikt voor', 'ideaal voor'];
            foreach ($treatment_terms as $term) {
                if (strpos($description, $term) !== false) {
                    $score += 0.5;
                }
            }
        }
        
        // 6. Ingredient/component matches
        if (!empty($product['ingredients'])) {
            foreach ($product['ingredients'] as $ingredient) {
                foreach ($keywords as $keyword) {
                    if (strpos(strtolower($ingredient), strtolower($keyword)) !== false) {
                        $score += 0.3;
                    }
                }
            }
        }
        
        // 7. Bonus for products with price (indicates completeness)
        if (!empty($product['price']) && $product['price'] > 0) {
            $score += 0.1;
        }
        
        return $score;
    }
    
    // Live WooCommerce product search used when local product tables are removed
    private function search_woocommerce_products($query) {
        if (!class_exists('WooCommerce')) {
            return [];
        }
        
        // Simple caching for product searches
        $cache_key = 'aipc_products_' . md5(strtolower($query));
        $cached_result = wp_cache_get($cache_key, 'aipc_products');
        if ($cached_result !== false) {
            return $cached_result;
        }
        // Try to guess a product title from the user message (e.g., "over product X")
        $guessed_title = $this->guess_product_title($query);

        // Normalize question to a likely title fragment (remove stock/status phrasing)
        $normalized_title = $this->normalize_title_query($query);

        // 1) Exact title match first (guessed or normalized)
        $title_candidates = array_values(array_unique(array_filter([$guessed_title, $normalized_title])));
        foreach ($title_candidates as $candidate) {
            $post = get_page_by_title($candidate, OBJECT, 'product');
            if ($post instanceof WP_Post) {
                $product = wc_get_product($post->ID);
                if ($product) {
                    return [$this->normalize_wc_product_entry($product)];
                }
            }
        }

        // 2) Case-insensitive LIKE on post_title for normalized candidate
        if (!empty($normalized_title)) {
            global $wpdb;
            $like = '%' . $wpdb->esc_like($normalized_title) . '%';
            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' AND post_title LIKE %s LIMIT 5",
                $like
            ));
            $results = [];
            if (!empty($ids)) {
                foreach ($ids as $pid) {
                    $product = wc_get_product($pid);
                    if ($product) { $results[] = $this->normalize_wc_product_entry($product); }
                }
                if (!empty($results)) { return $results; }
            }
        }

        // 3) Multiple search strategies - execute all early to catch more matches
        $all_results = [];
        
        // Extract terms for searches
        $termsForTitle = $this->extract_keywords_for_titles($query);
        
        // Smart term filtering: look for specific product/medical terms
        $specific_terms = [];
        $generic_words = ['welk', 'welke', 'wat', 'hoe', 'waar', 'wanneer', 'middel', 'middelen', 'product', 'producten', 'helpt', 'helpen', 'goed', 'beste', 'kan', 'kunnen', 'tegen', 'voor', 'bij', 'met', 'aan', 'in'];
        
        foreach ($termsForTitle as $term) {
            if (!in_array(strtolower($term), $generic_words) && strlen($term) >= 4) {
                $specific_terms[] = $term;
            }
        }
        
        // Use specific terms if found, otherwise fall back to original terms
        $searchTerms = !empty($specific_terms) ? $specific_terms : $termsForTitle;
        
        
        // Always do description search for all queries
        if (!empty($termsForTitle)) {
            $found = $this->search_wc_by_description_terms($termsForTitle, 25); // Increased from 15 to 25
            foreach ($found as $prod) {
                $all_results[$prod->get_id()] = $this->normalize_wc_product_entry($prod);
            }
        }
        
        // 3a) Keyword search using WP 's' (title + content)
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            's' => $query,
            'posts_per_page' => 20 // Increased from 10 to 20
        ];
        $posts = get_posts($args);
        foreach ($posts as $post) {
            $product = wc_get_product($post->ID);
            if ($product) {
                $all_results[$product->get_id()] = $this->normalize_wc_product_entry($product);
            }
        }
        
        // 3b) Title search using filtered terms
        if (!empty($searchTerms)) {
            $titleMatches = $this->search_wc_by_title_terms($searchTerms, 20); // Increased from 10 to 20
            foreach ($titleMatches as $prod) {
                $all_results[$prod->get_id()] = $this->normalize_wc_product_entry($prod);
            }
        }
        
        // 3c) Description search using filtered terms
        if (!empty($searchTerms)) {
            $found = $this->search_wc_by_description_terms($searchTerms, 25); // Increased from 15 to 25
            foreach ($found as $prod) {
                $all_results[$prod->get_id()] = $this->normalize_wc_product_entry($prod);
            }
        }
        
        // Convert to simple array
        $results = array_values($all_results);
        
        // 4) If still nothing, try broader keyword search
        if (empty($results)) {
            if (!empty($searchTerms)) {
                $args2 = [
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    's' => implode(' ', array_slice($searchTerms, 0, 4)),
                    'posts_per_page' => 20 // Increased from 10 to 20
                ];
                $posts2 = get_posts($args2);
                foreach ($posts2 as $post2) {
                    $p = wc_get_product($post2->ID);
                    if ($p) $results[] = $this->normalize_wc_product_entry($p);
                }
            }
        }
        
        
        // 6) Ingredient meta search using configured field (e.g., _ingredients)
        if (empty($results)) {
            $ingredientField = get_option('aipc_woocommerce_ingredients_field', '_ingredients');
            if (!empty($ingredientField) && !empty($searchTerms)) {
                $found = $this->search_wc_by_ingredient_terms($searchTerms, $ingredientField, 20); // Increased from 10 to 20
                foreach ($found as $prod) {
                    $results[] = $this->normalize_wc_product_entry($prod);
                }
            }
        }

        // 7) Fuzzy title similarity over recent products (token overlap including single-letter tokens)
        if (empty($results)) {
            $needleTokens = $this->tokenize_title($normalized_title ?: $query);
            if (!empty($needleTokens)) {
                $scanArgs = [
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'posts_per_page' => 100,
                    'orderby' => 'date',
                    'order' => 'DESC'
                ];
                $scanPosts = get_posts($scanArgs);
                $scored = [];
                foreach ($scanPosts as $sp) {
                    $prod = wc_get_product($sp->ID);
                    if (!$prod) continue;
                    $titleTokens = $this->tokenize_title($prod->get_name());
                    $score = $this->jaccard_similarity($needleTokens, $titleTokens);
                    // Boost if all needle tokens appear in order in title string
                    $titleLower = strtolower($prod->get_name());
                    $inOrder = true;
                    $pos = 0;
                    foreach ($needleTokens as $tok) {
                        $found = strpos($titleLower, $tok, $pos);
                        if ($found === false) { $inOrder = false; break; }
                        $pos = $found + strlen($tok);
                    }
                    if ($inOrder) { $score += 0.2; }
                    if ($score >= 0.34) { // modest threshold to catch e.g. "vitamine b complex"
                        $scored[] = [$score, $prod];
                    }
                }
                if (!empty($scored)) {
                    usort($scored, function($a, $b) { return $a[0] < $b[0] ? 1 : ($a[0] > $b[0] ? -1 : 0); });
                    foreach (array_slice($scored, 0, 10) as $pair) { // Increased from 5 to 10
                        $results[] = $this->normalize_wc_product_entry($pair[1]);
                    }
                }
            }
        }
        
        // Cache the results for 5 minutes
        wp_cache_set($cache_key, $results, 'aipc_products', 300);
        
        return $results;
    }

    private function search_wc_by_title_terms(array $terms, $limit = 10) {
        global $wpdb;
        $terms = array_values(array_filter(array_unique(array_map('strtolower', $terms))));
        // Filter trivial/common words that can pollute title LIKE
        $stop = ['verkopen','jullie','you','sell','heb','hebt','heeft','is','zijn','op','voorraad'];
        $titleIds = [];
        foreach ($terms as $t) {
            if (in_array($t, $stop, true)) { continue; }
            if (mb_strlen($t, 'UTF-8') < 3) { continue; }
            $like = '%' . $wpdb->esc_like($t) . '%';
            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' AND post_title LIKE %s LIMIT %d",
                $like,
                $limit
            ));
            if (!empty($ids)) { $titleIds = array_merge($titleIds, $ids); }
        }
        $titleIds = array_values(array_unique(array_map('intval', $titleIds)));
        $out = [];
        foreach (array_slice($titleIds, 0, $limit) as $pid) {
            $p = wc_get_product($pid);
            if ($p) { $out[] = $p; }
        }
        return $out;
    }

    private function search_wc_by_description_terms(array $terms, $limit = 10) {
        global $wpdb;
        $terms = array_values(array_filter(array_unique(array_map('strtolower', $terms))));
        if (empty($terms)) { return []; }
        
        $descriptionIds = [];
        foreach ($terms as $t) {
            if (mb_strlen($t, 'UTF-8') < 3) { 
                continue; 
            }
            $like = '%' . $wpdb->esc_like($t) . '%';
            
            // Search in both short and long description (post_excerpt and post_content)
            $sql = $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                WHERE post_type='product' AND post_status='publish' 
                AND (post_excerpt LIKE %s OR post_content LIKE %s) 
                LIMIT %d",
                $like,
                $like,
                $limit
            );
            
            $ids = $wpdb->get_col($sql);
            
            if (!empty($ids)) { 
                $descriptionIds = array_merge($descriptionIds, $ids); 
            }
        }
        
        $descriptionIds = array_values(array_unique(array_map('intval', $descriptionIds)));
        $out = [];
        foreach (array_slice($descriptionIds, 0, $limit) as $pid) {
            $p = wc_get_product($pid);
            if ($p) { $out[] = $p; }
        }
        return $out;
    }
    
    private function get_related_product_suggestions($failed_keywords) {
        if (!class_exists('WooCommerce')) {
            return [];
        }
        
        // Define related term mappings
        $related_terms = [
            // Health/Medical
            'hart' => ['cardiovasculair', 'bloeddruk', 'cholesterol', 'circulatie'],
            'bloedsomloop' => ['hart', 'bloeddruk', 'cardiovasculair', 'circulatie'],
            'cholesterol' => ['hart', 'bloedsomloop', 'cardiovasculair'],
            'bloeddruk' => ['hart', 'cardiovasculair', 'bloedsomloop'],
            'diabeet' => ['bloedsuiker', 'glucose', 'metabool', 'suiker'],
            'diabetes' => ['bloedsuiker', 'glucose', 'metabool', 'suiker'],
            'bloedsuiker' => ['diabeet', 'diabetes', 'glucose', 'metabool'],
            'glucose' => ['bloedsuiker', 'suiker', 'diabeet'],
            
            // Beauty/Skincare
            'huid' => ['gezicht', 'lichaam', 'verzorging', 'crème'],
            'anti-aging' => ['rimpels', 'veroudering', 'collageen', 'elastine'],
            'acne' => ['puistjes', 'onzuiverheden', 'poriën'],
            'droge huid' => ['hydratie', 'vocht', 'dehydratie'],
            'vette huid' => ['talg', 'glans', 'poriën'],
            
            // Health/Wellness
            'energie' => ['vitaliteit', 'vermoeidheid', 'conditie', 'moe'],
            'slaap' => ['rust', 'ontspanning', 'stress', 'insomnia'],
            'gewicht' => ['afvallen', 'dieet', 'metabolisme'],
            'immuun' => ['weerstand', 'afweer', 'gezondheid'],
            'stress' => ['ontspanning', 'rust', 'spanning', 'slaap'],
            'vermoeidheid' => ['energie', 'vitaliteit', 'moe'],
            'hoofdpijn' => ['pijn', 'spanning', 'stress'],
            'gewrichten' => ['pijn', 'artritis', 'beweging', 'stijfheid']
        ];
        
        $suggestions = [];
        
        foreach ($failed_keywords as $keyword) {
            $keyword_lower = strtolower($keyword);
            
            // Find related terms for this keyword
            $related = [];
            foreach ($related_terms as $base => $variants) {
                if ($keyword_lower === $base || in_array($keyword_lower, $variants)) {
                    $related = array_merge([$base], $variants);
                    break;
                }
                // Also check if keyword partially matches
                foreach ($variants as $variant) {
                    if (strpos($keyword_lower, $variant) !== false || strpos($variant, $keyword_lower) !== false) {
                        $related = array_merge([$base], $variants);
                        break 2;
                    }
                }
            }
            
            // Search for products with related terms
            foreach ($related as $related_term) {
                if ($related_term === $keyword_lower) continue; // Skip the failed keyword itself
                
                $count = $this->count_products_with_term($related_term);
                if ($count > 0) {
                    $suggestions[] = [
                        'term' => $related_term,
                        'count' => $count
                    ];
                }
            }
        }
        
        // Remove duplicates and limit to top 5
        $unique_suggestions = [];
        foreach ($suggestions as $suggestion) {
            $unique_suggestions[$suggestion['term']] = $suggestion;
        }
        
        // Sort by count (most products first)
        uasort($unique_suggestions, function($a, $b) {
            return $a['count'] < $b['count'] ? 1 : ($a['count'] > $b['count'] ? -1 : 0);
        });
        
        return array_slice(array_values($unique_suggestions), 0, 5);
    }
    
    private function count_products_with_term($term) {
        global $wpdb;
        
        $like = '%' . $wpdb->esc_like($term) . '%';
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT ID) FROM {$wpdb->posts} 
            WHERE post_type='product' AND post_status='publish' 
            AND (post_title LIKE %s OR post_excerpt LIKE %s OR post_content LIKE %s)",
            $like,
            $like,
            $like
        ));
        
        return intval($count);
    }
    
    private function get_ingredient_variations($ingredient) {
        $variations = [$ingredient];
        
        // Add common trema/accent variations
        $replacements = [
            'ë' => 'e',
            'ï' => 'i',
            'ö' => 'o',
            'ü' => 'u',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'á' => 'a',
            'à' => 'a',
            'â' => 'a',
            'ó' => 'o',
            'ò' => 'o',
            'ô' => 'o'
        ];
        
        // Generate variation without accents/tremas
        $no_accents = $ingredient;
        foreach ($replacements as $accented => $plain) {
            $no_accents = str_replace($accented, $plain, $no_accents);
        }
        if ($no_accents !== $ingredient) {
            $variations[] = $no_accents;
        }
        
        // Generate variation with accents (reverse mapping)
        $with_accents = $ingredient;
        $reverse_replacements = array_flip($replacements);
        // Special cases for common ingredients
        if (stripos($ingredient, 'aloe vera') !== false) {
            $variations[] = str_ireplace('aloe vera', 'aloë vera', $ingredient);
        }
        if (stripos($ingredient, 'aloe') !== false && stripos($ingredient, 'vera') !== false) {
            $variations[] = str_ireplace('aloe', 'aloë', $ingredient);
        }
        
        return array_unique($variations);
    }
    
    private function search_wc_by_ingredient_terms(array $terms, $metaKey, $limit = 10) {
        $terms = array_values(array_filter(array_unique(array_map('strtolower', $terms))));
        if (empty($terms)) { return []; }
        $metaQueries = [ 'relation' => 'OR' ];
        foreach ($terms as $t) {
            if (mb_strlen($t, 'UTF-8') < 3) { continue; }
            
            // Add the original term
            $metaQueries[] = [
                'key' => $metaKey,
                'value' => $t,
                'compare' => 'LIKE'
            ];
            
            // Add variations for trema/accent differences
            $variations = $this->get_ingredient_variations($t);
            foreach ($variations as $variation) {
                if ($variation !== $t) {
                    $metaQueries[] = [
                        'key' => $metaKey,
                        'value' => $variation,
                        'compare' => 'LIKE'
                    ];
                }
            }
        }
        if (count($metaQueries) <= 1) { return []; }
        $q = new WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => $metaQueries
        ]);
        $out = [];
        if ($q->have_posts()) {
            foreach ($q->posts as $p) {
                $prod = wc_get_product($p->ID);
                if ($prod) { $out[] = $prod; }
            }
        }
        wp_reset_postdata();
        return $out;
    }

    private function normalize_wc_product_entry($product) {
        $short = $product->get_short_description() ?: '';
        $full = $product->get_description() ?: '';
        $combined = trim($short) && trim($full) ? ($short . "\n" . $full) : ($short ?: $full);
        $entry = [
            'name' => $product->get_name(),
            'description' => $combined,
            'ingredients' => [],
            'skin_types' => [],
            'price' => $product->get_price(),
            'image_url' => wp_get_attachment_url($product->get_image_id()),
            'product_url' => get_permalink($product->get_id()),
            'stock_status' => $product->get_stock_status()
        ];
        // Populate ingredients/skin_types from configured meta fields if available
        $ingredientsField = get_option('aipc_woocommerce_ingredients_field', '_ingredients');
        $skinTypesField = get_option('aipc_woocommerce_skin_types_field', '_skin_types');
        
        // Try to get ingredients from configured field or common ACF field names
        $ingredient_data = null;
        $possible_fields = [$ingredientsField, 'Ingrediënten', '_Ingrediënten', 'ingredients', 'ingredienten', 'product_ingredients', '_ingredients', 'product_ingrediënten', 'product_ingredienten'];
        
        foreach ($possible_fields as $field_name) {
            if (empty($field_name)) continue;
            $raw = get_post_meta($product->get_id(), $field_name, true);
            
            // Check if this is an ACF field reference (starts with 'field_')
            if (is_string($raw) && strpos($raw, 'field_') === 0) {
                // Try to get the actual field value using ACF if available
                if (function_exists('get_field')) {
                    // Try to get the field by its key
                    $acf_value = get_field($raw, $product->get_id());
                    if (!empty($acf_value)) {
                        $ingredient_data = $acf_value;
                        break;
                    }
                    // Also try getting the field by the original field name
                    $acf_value = get_field($field_name, $product->get_id());
                    if (!empty($acf_value)) {
                        $ingredient_data = $acf_value;
                        break;
                    }
                }
            } elseif (!empty($raw)) {
                $ingredient_data = $raw;
                break;
            }
        }
        
        if (!empty($ingredient_data)) {
            if (is_string($ingredient_data)) {
                $vals = json_decode($ingredient_data, true);
                if (is_array($vals)) {
                    $entry['ingredients'] = array_values(array_filter(array_map('trim', $vals)));
                } else {
                    // assume comma or pipe separated
                    $parts = preg_split('/[,|\n]+/', $ingredient_data);
                    if (is_array($parts)) {
                        $entry['ingredients'] = array_values(array_filter(array_map('trim', $parts)));
                    }
                }
            } elseif (is_array($ingredient_data)) {
                // Direct array from ACF
                $entry['ingredients'] = array_values(array_filter(array_map('trim', $ingredient_data)));
            }
        }
        
        // Always extract ingredients from product descriptions (merge with existing)
        if (!empty($combined)) {
            $extracted_ingredients = $this->extract_ingredients_from_description($combined);
            if (!empty($extracted_ingredients)) {
                $entry['ingredients'] = array_unique(array_merge($entry['ingredients'], $extracted_ingredients));
            }
        }
        
        if (!empty($skinTypesField)) {
            $raw2 = get_post_meta($product->get_id(), $skinTypesField, true);
            if (is_string($raw2) && $raw2 !== '') {
                $vals2 = json_decode($raw2, true);
                if (is_array($vals2)) {
                    $entry['skin_types'] = array_values(array_filter(array_map('trim', $vals2)));
                } else {
                    $parts2 = preg_split('/[,|\n]+/', $raw2);
                    if (is_array($parts2)) {
                        $entry['skin_types'] = array_values(array_filter(array_map('trim', $parts2)));
                    }
                }
            }
        }
        if ($product->is_type('variable')) {
            $min = $product->get_variation_price('min');
            $max = $product->get_variation_price('max');
            $entry['price'] = ($min && $max && $min != $max) ? ($min . ' - ' . $max) : $min;
            $attrs = $product->get_variation_attributes();
            $pairs = [];
            foreach ($attrs as $tax => $values) {
                $label = function_exists('wc_attribute_label') ? wc_attribute_label($tax) : sanitize_text_field($tax);
                $values = is_array($values) ? $values : [$values];
                $pairs[] = $label . ': ' . implode(', ', array_map('sanitize_text_field', array_filter($values)));
            }
            if (!empty($pairs)) {
                $entry['description'] .= "\n" . implode(' | ', $pairs);
            }
        }
        return $entry;
    }

    private function guess_product_title($message) {
        $m = trim($message);
        // capture quoted word or phrase
        if (preg_match('/["\'\`](.+?)["\'\`]/u', $m, $mm)) {
            return trim($mm[1]);
        }
        // capture after the word 'product'
        if (preg_match('/product\s+([\p{L}0-9\-\s]+)(\?|\.|$)/iu', $m, $mm2)) {
            return trim(preg_replace('/[\p{P}\p{S}]+$/u', '', $mm2[1]));
        }
        return '';
    }

    private function normalize_title_query($message) {
        $t = strtolower(trim($message));
        // Remove common question/stock phrases
        $t = preg_replace('/\b(is|staat|ligt|hebben we|heb je|heeft u)\b/iu', ' ', $t);
        $t = preg_replace('/\b(op voorraad|voorraad|beschikbaar|availability|in stock)\b/iu', ' ', $t);
        // Remove question words and punctuation
        $t = preg_replace('/[\?\!\.,]+/u', ' ', $t);
        $t = preg_replace('/\s+/u', ' ', $t);
        $t = trim($t);
        return $t;
    }

    private function tokenize_title($text) {
        $t = strtolower($text);
        // Keep letters, numbers, and single-letter tokens (e.g., "b" in "vitamine b complex")
        $parts = preg_split('/\s+/u', preg_replace('/[^\p{L}0-9\s]/u', ' ', $t));
        $tokens = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;
            // Allow single letters if alphabetic
            if (mb_strlen($p, 'UTF-8') === 1) {
                if (preg_match('/[a-z0-9]/u', $p)) { $tokens[] = $p; }
                continue;
            }
            // Skip trivial Dutch/English stopwords for titles
            $stop = ['de','het','een','en','of','voor','met','op','aan','in','van','the','a','an','and','or'];
            if (in_array($p, $stop, true)) continue;
            $tokens[] = $p;
        }
        // Deduplicate
        return array_values(array_unique($tokens));
    }

    private function extract_keywords_for_titles($message) {
        // Like extract_keywords but preserve meaningful single letters
        $lower = strtolower($message);
        $words = preg_split('/\s+/u', preg_replace('/^[\p{P}\p{S}]+|[\p{P}\p{S}]+$/u', ' ', $lower));
        $cleaned = [];
        $stop = [
            'de','het','een','en','of','voor','met','op','aan','in','uit','over','onder','naar','bij','van','tot','als','dan','maar','ook','dus','toch','al','nog','wel','niet','geen','is','op','voorraad','beschikbaar','availability','in','stock','product','producten','vraag','vragen'
        ];
        foreach ($words as $w) {
            $w = trim($w);
            if ($w === '' || in_array($w, $stop, true)) continue;
            // Keep single-letter tokens (e.g., 'b') if alphabetic
            if (mb_strlen($w, 'UTF-8') === 1) {
                if (preg_match('/[a-z]/u', $w)) { $cleaned[] = $w; }
                continue;
            }
            if (mb_strlen($w, 'UTF-8') >= 2) {
                $cleaned[] = $w;
            }
        }
        return array_values(array_unique($cleaned));
    }
    
    private function extract_ingredients_from_description($description) {
        if (empty($description)) {
            return [];
        }
        
        $ingredients = [];
        $text = strtolower($description);
        
        // Common ingredient patterns to look for (including Dutch variations)
        $ingredient_patterns = [
            // Compound names
            'aloe vera', 'aloë vera', 'hyaluronic acid', 'hyaluronzuur',
            'vitamin c', 'vitamine c', 'vitamin e', 'vitamine e',
            'vitamin a', 'vitamine a', 'vitamin b', 'vitamine b',
            'salicylic acid', 'salicylzuur', 'lactic acid', 'melkzuur',
            'glycolic acid', 'glycolzuur', 'citric acid', 'citroenzuur',
            'tea tree', 'shea butter', 'shea boter', 'cocoa butter', 'cacoaboter', 'niacinamide',
            'retinol', 'ceramide', 'peptide', 'collageen', 'collagen',
            'glycerine', 'glycerin', 'panthenol', 'tocopherol', 'tocopherol',
            'zinkoxide', 'zinc oxide', 'bijenwas', 'cera alba',
            'zonnebloemolie', 'helianthus annuus', 'kokosnootolie', 'cocos nucifera',
            // Single names
            'zinc', 'zink', 'magnesium', 'calcium', 'iron', 'selenium',
            'biotin', 'keratin', 'elastin', 'urea', 'allantoin'
        ];
        
        foreach ($ingredient_patterns as $ingredient) {
            if (strpos($text, $ingredient) !== false) {
                // Preserve original case from pattern
                $ingredients[] = $ingredient;
            }
        }
        
        return array_unique($ingredients);
    }
}
