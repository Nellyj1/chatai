<?php
class AIPC_Skin_Test {

	private function get_default_steps() {
		return [
			[
				'key' => 'skin_type',
				'question' => 'Welke beschrijving past het best bij jouw huid?',
				'options' => ['Droog', 'Vet', 'Gecombineerd', 'Gevoelig', 'Normaal']
			],
			[
				'key' => 'concerns',
				'question' => 'Wat zijn je belangrijkste huidzorgen?',
				'options' => ['Puistjes/onzuiverheden', 'Roodheid/irritatie', 'Fijne lijntjes', 'Doffe teint', 'Grove poriën']
			],
			[
				'key' => 'sensitivity',
				'question' => 'Reageert je huid snel gevoelig op producten?',
				'options' => ['Ja, vaak', 'Soms', 'Zelden/nooit']
			],
			[
				'key' => 'spf',
				'question' => 'Gebruik je dagelijks SPF?',
				'options' => ['Ja', 'Nee', 'Soms']
			],
			[
				'key' => 'budget',
				'question' => 'Wat is je budget per product?',
				'options' => ['< €20', '€20–€40', '> €40']
			]
		];
	}

	private function steps() {
		try {
			$json = trim(get_option('aipc_skin_test_questions', ''));
			
			if (!empty($json)) {
				$data = json_decode($json, true);
				if (json_last_error() === JSON_ERROR_NONE && is_array($data) && !empty($data)) {
					$valid_steps = [];
					foreach ($data as $index => $step) {
						if (!is_array($step)) continue;
						if (!isset($step['key']) || !is_string($step['key']) || empty($step['key'])) continue;
						if (!isset($step['question']) || !is_string($step['question']) || empty($step['question'])) continue;
						if (!isset($step['options']) || !is_array($step['options']) || empty($step['options'])) continue;
						
						$valid_options = [];
						foreach ($step['options'] as $option) {
							if (is_string($option) && !empty(trim($option))) {
								$valid_options[] = trim($option);
							}
						}
						
						if (empty($valid_options)) continue;
						$step['options'] = $valid_options;
						$valid_steps[] = $step;
					}
					
					if (!empty($valid_steps)) {
						return $valid_steps;
					}
				}
			}
			
			return $this->get_default_steps();
			
		} catch (Exception $e) {
			error_log('AIPC_Skin_Test::steps - Fout: ' . $e->getMessage());
			return $this->get_default_steps();
		}
	}

	private function tk($conversation_id) {
		return 'aipc_skin_test_' . $conversation_id;
	}

	public function reset($conversation_id) {
		if (!empty($conversation_id)) {
			delete_transient($this->tk($conversation_id));
		}
	}

	private function load_state($conversation_id) {
		if (empty($conversation_id)) {
			return ['step' => 0, 'answers' => []];
		}
		
		$state = get_transient($this->tk($conversation_id));
		if (!is_array($state)) {
			$state = ['step' => 0, 'answers' => []];
		}
		
		if (!isset($state['answers']) || !is_array($state['answers'])) {
			$state['answers'] = [];
		}
		
		if (!isset($state['step']) || !is_numeric($state['step'])) {
			$state['step'] = 0;
		}
		
		return $state;
	}

	private function save_state($conversation_id, $state) {
		if (empty($conversation_id) || !is_array($state)) {
			return;
		}
		set_transient($this->tk($conversation_id), $state, 2 * HOUR_IN_SECONDS);
	}

	public function start($conversation_id) {
		try {
			if (empty($conversation_id)) {
				$conversation_id = wp_generate_uuid4();
			}
			
			$this->reset($conversation_id);
			$steps = $this->steps();
			
			if (empty($steps) || !is_array($steps)) {
				throw new Exception('Geen geldige teststappen beschikbaar');
			}
			
			$state = ['step' => 0, 'answers' => []];
			$first_step_index = $this->next_visible_step_index($state['answers'], 0);
			$state['step'] = $first_step_index;
			
			if ($first_step_index >= count($steps)) {
				$state['step'] = 0;
			}
			
			$this->save_state($conversation_id, $state);
			return $this->response_step($conversation_id, $state);
			
		} catch (Exception $e) {
			error_log('AIPC_Skin_Test::start - Fout: ' . $e->getMessage());
			return [
				'message' => __('Sorry, er is een fout opgetreden bij het starten van de huidtest. Probeer het later nog eens.', 'ai-product-chatbot'),
				'skin_test' => ['active' => false],
				'quick_replies' => []
			];
		}
	}

	public function handle_answer($conversation_id, $user_message) {
		try {
			if (empty($conversation_id)) {
				$conversation_id = wp_generate_uuid4();
			}
			
			if (!is_string($user_message)) {
				$user_message = '';
			}
			
			$state = $this->load_state($conversation_id);
			$steps = $this->steps();
			
			if (empty($steps) || !is_array($steps)) {
				throw new Exception('Geen geldige teststappen beschikbaar');
			}
			
			$idx = isset($state['step']) && is_numeric($state['step']) ? (int)$state['step'] : 0;
			
			if ($idx >= count($steps)) {
				return $this->finalize($conversation_id, $state);
			}
			
			if (!$this->is_step_visible($steps[$idx], $state['answers'])) {
				$idx = $this->next_visible_step_index($state['answers'], $idx);
				$state['step'] = $idx;
				$this->save_state($conversation_id, $state);
				if ($idx >= count($steps)) { 
					return $this->finalize($conversation_id, $state); 
				}
			}
			
			$step = $steps[$idx];
			
			if (!is_array($step)) {
				throw new Exception('Ongeldige step structuur');
			}
			
			if (!isset($step['options']) || !is_array($step['options'])) {
				$step['options'] = [];
			}
			
			if (!isset($step['key']) || !is_string($step['key'])) {
				$step['key'] = 'step_' . $idx;
			}
			
			$normalized = strtolower(trim($user_message));
			$chosen = null;
			
			foreach ($step['options'] as $opt) {
				if (is_string($opt) && strpos($normalized, strtolower($opt)) !== false) { 
					$chosen = $opt; 
					break; 
				}
			}
			
			if (!$chosen) { 
				$chosen = trim($user_message); 
			}
			
			$state['answers'][$step['key']] = $chosen;
			$state['step'] = $this->next_visible_step_index($state['answers'], $idx + 1);
			$this->save_state($conversation_id, $state);
			
			if ($state['step'] >= count($steps)) {
				return $this->finalize($conversation_id, $state);
			}
			
			return $this->response_step($conversation_id, $state);
			
		} catch (Exception $e) {
			error_log('AIPC_Skin_Test::handle_answer - Fout: ' . $e->getMessage());
			return [
				'message' => __('Sorry, er is een fout opgetreden bij het verwerken van je antwoord. Probeer het later nog eens.', 'ai-product-chatbot'),
				'skin_test' => ['active' => false]
			];
		}
	}

	private function response_step($conversation_id, $state) {
		try {
			if (empty($conversation_id)) {
				$conversation_id = wp_generate_uuid4();
			}
			
			if (!is_array($state)) {
				$state = ['step' => 0, 'answers' => []];
			}
			
			if (!isset($state['answers']) || !is_array($state['answers'])) {
				$state['answers'] = [];
			}
			
			$steps = $this->steps();
			if (!is_array($steps) || empty($steps)) {
				throw new Exception('Geen geldige teststappen beschikbaar');
			}
			
			$idx = isset($state['step']) && is_numeric($state['step']) ? (int)$state['step'] : 0;
			
			if ($idx < count($steps) && !$this->is_step_visible($steps[$idx], $state['answers'])) {
				$idx = $this->next_visible_step_index($state['answers'], $idx);
				$state['step'] = $idx;
				$this->save_state($conversation_id, $state);
			}
			
			if ($idx >= count($steps)) { 
				return $this->finalize($conversation_id, $state); 
			}
			
			$step = $steps[$idx];
			
			if (!is_array($step)) {
				throw new Exception('Ongeldige step structuur');
			}
			
			if (!isset($step['question']) || !is_string($step['question']) || empty($step['question'])) {
				$step['question'] = 'Wat is je voorkeur?';
			}
			
			if (!isset($step['key']) || !is_string($step['key']) || empty($step['key'])) {
				$step['key'] = 'step_' . $idx;
			}
			
			if (!isset($step['options']) || !is_array($step['options']) || empty($step['options'])) {
				$step['options'] = ['Ja', 'Nee'];
			}
			
			return [
				'message' => $step['question'],
				'skin_test' => [
					'active' => true,
					'step' => $idx,
					'total' => count($steps),
					'key' => $step['key']
				],
				'quick_replies' => $step['options']
			];
			
		} catch (Exception $e) {
			error_log('AIPC_Skin_Test::response_step - Fout: ' . $e->getMessage());
			return [
				'message' => __('Sorry, er is een fout opgetreden bij het laden van de huidtest vraag. Probeer het later nog eens.', 'ai-product-chatbot'),
				'skin_test' => ['active' => false],
				'quick_replies' => ['Nieuwe huidtest']
			];
		}
	}

	private function is_step_visible($step, $answers) {
		try {
			if (!is_array($step)) {
				return true;
			}
			
			if (!isset($step['show_if'])) {
				return true;
			}
			
			$cond = $step['show_if'];
			if (!is_array($cond)) {
				return true;
			}
			
			if (!is_array($answers)) {
				$answers = [];
			}
			
			foreach ($cond as $key => $expected) {
				if (!is_string($key)) {
					continue;
				}
				
				$actual = isset($answers[$key]) ? strtolower((string)$answers[$key]) : '';
				
				if (is_array($expected)) {
					$ok = false;
					foreach ($expected as $needle) {
						$needle = strtolower((string)$needle);
						if ($needle !== '' && strpos($actual, $needle) !== false) { 
							$ok = true; 
							break; 
						}
					}
					if (!$ok) { 
						return false; 
					}
				} else {
					$needle = strtolower((string)$expected);
					if ($needle !== '' && strpos($actual, $needle) === false) { 
						return false; 
					}
				}
			}
			
			return true;
		} catch (Exception $e) {
			error_log('AIPC_Skin_Test::is_step_visible - Fout: ' . $e->getMessage());
			return true;
		}
	}

	private function next_visible_step_index($answers, $fromIndex) {
		try {
			$steps = $this->steps();
			if (!is_array($steps)) {
				return 0;
			}
			
			if (!is_array($answers)) {
				$answers = [];
			}
			
			$idx = max(0, (int)$fromIndex);
			$len = count($steps);
			
			while ($idx < $len) {
				if (!isset($steps[$idx])) {
					$idx++;
					continue;
				}
				
				if ($this->is_step_visible($steps[$idx], $answers)) { 
					return $idx; 
				}
				$idx++;
			}
			
			return $len;
		} catch (Exception $e) {
			error_log('AIPC_Skin_Test::next_visible_step_index - Fout: ' . $e->getMessage());
			return max(0, (int)$fromIndex);
		}
	}

	private function finalize($conversation_id, $state) {
		try {
			if (empty($conversation_id)) {
				$conversation_id = 'fallback_' . uniqid();
			}
			
			if (!is_array($state)) {
				$state = ['step' => 0, 'answers' => []];
			}
			
			if (!isset($state['answers']) || !is_array($state['answers'])) {
				$state['answers'] = [];
			}
			
			try {
				$profile = $this->derive_profile($state['answers']);
			} catch (Exception $e) {
				error_log('AIPC_Skin_Test::finalize - Fout bij derive_profile: ' . $e->getMessage());
				$profile = [
					'label' => 'Algemeen',
					'summary' => 'Op basis van je antwoorden adviseren we een milde, effectieve routine.',
					'answers' => $state['answers'],
					'products' => []
				];
			}
			
			try {
				$recs = $this->recommend_products($profile, 50, 0);
			} catch (Exception $e) {
				error_log('AIPC_Skin_Test::finalize - Fout bij recommend_products: ' . $e->getMessage());
				$recs = [];
			}
			
            if (is_array($profile) && isset($profile['answers'])) {
				set_transient('aipc_skin_last_' . $conversation_id, [
					'profile' => $profile,
					'answers' => $profile['answers']
				], 2 * HOUR_IN_SECONDS);
			}

            // Append lightweight analytics item for admin overview (recent matched rules)
            try {
                $labelForLog = isset($profile['label']) ? (string)$profile['label'] : '';
                $summaryForLog = isset($profile['summary']) ? (string)$profile['summary'] : '';
                if ($labelForLog !== '' || $summaryForLog !== '') {
                    $key = 'aipc_skin_latest_rules';
                    $existing = get_transient($key);
                    if (!is_array($existing)) { $existing = []; }
                    array_unshift($existing, [
                        'label' => $labelForLog,
                        'summary' => $summaryForLog,
                        'ts' => current_time('mysql')
                    ]);
                    // cap list size
                    if (count($existing) > 10) { $existing = array_slice($existing, 0, 10); }
                    set_transient($key, $existing, DAY_IN_SECONDS);
                }
            } catch (Exception $e) {
                // ignore analytics logging failure
            }
			
			$this->reset($conversation_id);
			
			$label = isset($profile['label']) ? $profile['label'] : 'Algemeen';
			$summary = isset($profile['summary']) ? $profile['summary'] : 'Op basis van je antwoorden adviseren we een milde, effectieve routine.';
			
			$msg = "Huidprofiel: " . $label . "\n"
				. "Samenvatting: " . $summary . "\n\n";
				
			if (empty($recs)) {
				$msg .= "Voor dit specifieke huidprofiel hebben we nog geen productaanbevelingen geconfigureerd.\n\n";
				$msg .= "Neem gerust contact met ons op voor persoonlijk advies, of stel specifieke vragen over huidverzorging en ingrediënten.\n";
				$quick_replies = ['Contact opnemen', 'Nieuwe huidtest', 'Vraag over ingrediënten'];
			} else {
				$msg .= "Aanbevolen producten:\n";
				$msg .= implode("\n", array_map(function($p){
					if (!is_array($p)) return "- Product informatie niet beschikbaar";
					
					$product_name = html_entity_decode($p['name'] ?? 'Onbekend product', ENT_QUOTES, 'UTF-8');
					$price_text = '';
					if (!empty($p['price']) && is_numeric($p['price'])) {
						$price = number_format((float)$p['price'], 2);
						$price_text = " (€" . $price . ")";
					}
					
					if (!empty($p['url']) && filter_var($p['url'], FILTER_VALIDATE_URL)) {
						// Link de naam + prijs aan de URL
						return "- [" . $product_name . $price_text . "](" . $p['url'] . ")";
					} else {
						// Geen URL, toon alleen naam + prijs
						return "- " . $product_name . $price_text;
					}
				}, $recs));
				
				$quick_replies = ['Nieuwe huidtest'];
			}
			
			return [
				'message' => $msg,
				'skin_test' => ['active' => false, 'done' => true, 'profile' => $profile],
				'quick_replies' => $quick_replies
			];
			
		} catch (Exception $e) {
			error_log('AIPC_Skin_Test::finalize - Onverwachte fout: ' . $e->getMessage());
			return [
				'message' => __('Sorry, er is een fout opgetreden bij het afronden van de huidtest. Probeer het later nog eens.', 'ai-product-chatbot'),
				'skin_test' => ['active' => false, 'done' => false],
				'quick_replies' => ['Nieuwe huidtest']
			];
		}
	}

	private function derive_profile($answers) {
		try {
			if (!is_array($answers)) {
				$answers = [];
			}
			
			$rules_json = trim(get_option('aipc_skin_test_mapping', ''));
			
			$label = 'Algemeen';
			$summary = 'Op basis van je antwoorden adviseren we milde, effectieve routine afgestemd op jouw huidtype.';
			$products = [];
			
			if ($rules_json) {
				$rules = json_decode($rules_json, true);
				if (json_last_error() === JSON_ERROR_NONE && is_array($rules)) {
					foreach ($rules as $r) {
						if (!isset($r['if'], $r['label'], $r['summary'])) continue;
						
						$matches = true;
						foreach ($r['if'] as $k => $needle) {
							$val = isset($answers[$k]) ? strtolower($answers[$k]) : '';
							if (strpos($val, strtolower($needle)) === false) { 
								$matches = false; 
								break; 
							}
						}
						
						if ($matches) { 
							$label = $r['label']; 
							$summary = $r['summary'];
							if (isset($r['products']) && is_array($r['products'])) {
								$products = array_map('intval', $r['products']);
							}
							break; 
						}
					}
				}
			}
			
			if ($label === 'Algemeen') {
				$whoFor = isset($answers['who_for']) ? strtolower($answers['who_for']) : '';
				$targetSelf = isset($answers['target_area_self']) ? strtolower($answers['target_area_self']) : '';
				$targetTeen = isset($answers['target_area_child_teen']) ? strtolower($answers['target_area_child_teen']) : '';

				$features = [];
				foreach ($answers as $k => $v) {
					if (is_string($k) && (strpos($k, 'face_features') === 0 || strpos($k, 'body_features') === 0 || strpos($k, 'child_features') === 0)) {
						$features[] = strtolower((string)$v);
					}
				}
				$featuresText = implode(' ', $features);

				if (strpos($targetSelf, 'gezicht') !== false || strpos($targetTeen, 'gezicht') !== false) {
					if (preg_match('/droog|schilfer|rood/i', $featuresText)) {
						$label = 'Gezicht – droge/gevoelige huid';
						$summary = 'Hydratatie (hyaluronzuur/ceramiden), kalmeren (aloë/kamille), barrièreherstel.';
					} elseif (preg_match('/onzuiver|acne|puist/i', $featuresText)) {
						$label = 'Gezicht – onzuiverheden/acne';
						$summary = 'Reiniging + BHA (salicylzuur), niacinamide; lichte niet-comedogene texturen.';
					} elseif (preg_match('/pigment/i', $featuresText)) {
						$label = 'Gezicht – pigment';
						$summary = 'Egaliseren (vitamine C/niacinamide), dagelijks SPF.';
					} elseif (preg_match('/normaal.*vet|normaal tot vet|vet/i', $featuresText)) {
						$label = 'Gezicht – normaal/vet';
						$summary = 'Lichte hydratie, niacinamide voor poriën/talg, matte texturen.';
					}
				} elseif (strpos($targetSelf, 'lichaam') !== false || strpos($targetTeen, 'lichaam') !== false || strpos($targetSelf, 'hoofdhuid') !== false) {
					if (preg_match('/droog|schilfer|eczeem|psoriasis|rood/i', $featuresText)) {
						$label = 'Lichaam/Hoofdhuid – droog/eczeem';
						$summary = 'Rijke, parfumvrije barrière-herstellers; douche-olie en balsems.';
					} elseif (preg_match('/normaal.*vet|normaal tot vet|vet/i', $featuresText)) {
						$label = 'Lichaam/Hoofdhuid – normaal/vet';
						$summary = 'Lichte, snel intrekkende formules; talgregulatie waar nodig.';
					}
				}

				if ($label === 'Algemeen') {
					$type = isset($answers['skin_type']) ? strtolower($answers['skin_type']) : '';
					$concerns = isset($answers['concerns']) ? strtolower($answers['concerns']) : '';
					
					if (strpos($type, 'droog') !== false) { 
						$label = 'Droge huid'; 
						$summary = 'Hydratatie (hyaluronzuur/glycerine/ceramiden) en barrièreherstel.'; 
					} elseif (strpos($type, 'vet') !== false) { 
						$label = 'Vette huid'; 
						$summary = 'Talgregulatie/poriën (salicylzuur/niacinamide), lichte texturen.'; 
					} elseif (strpos($type, 'gevoelig') !== false) { 
						$label = 'Gevoelige huid'; 
						$summary = 'Milde formules (aloë/kamille), vermijd irriterende stoffen.'; 
					} elseif (strpos($type, 'gecombineerd') !== false) { 
						$label = 'Gecombineerde huid'; 
						$summary = 'Licht hydrateren en talgreguleren op T-zone.'; 
					} elseif (strpos($type, 'normaal') !== false) { 
						$label = 'Normale huid'; 
						$summary = 'Basisroutine met milde reiniging en hydratatie.'; 
					}
					
					if (strpos($concerns, 'lijntjes') !== false) { 
						$summary .= ' Overweeg \'s avonds retinol/peptiden.'; 
					}
					if (strpos($concerns, 'puist') !== false || strpos($concerns, 'onzuiver') !== false) { 
						$summary .= ' BHA (salicylzuur) helpt bij onzuiverheden.'; 
					}
					if (strpos($concerns, 'rood') !== false || strpos($concerns, 'irrit') !== false) { 
						$summary .= ' Kies kalmerende ingrediënten tegen roodheid.'; 
					}
				}
			}
			
			return [
				'label' => $label, 
				'summary' => $summary, 
				'answers' => $answers,
				'products' => $products
			];
		} catch (Exception $e) {
			error_log('AIPC_Skin_Test::derive_profile - Fout: ' . $e->getMessage());
			return [
				'label' => 'Algemeen',
				'summary' => 'Op basis van je antwoorden adviseren we een milde, effectieve routine.',
				'answers' => is_array($answers) ? $answers : [],
				'products' => []
			];
		}
	}

	public function recommend_products($profile, $limit = 5, $offset = 0) {
		$out = [];
		
		if (!is_array($profile)) {
			return $out;
		}
		
		$limit = max(1, (int)$limit);
		$offset = max(0, (int)$offset);
		
		if (!isset($profile['label'])) {
			$profile['label'] = 'Algemeen';
		}
		if (!isset($profile['answers']) || !is_array($profile['answers'])) {
			$profile['answers'] = [];
		}
		
		if (isset($profile['products']) && is_array($profile['products']) && !empty($profile['products'])) {
			if (class_exists('WooCommerce')) {
				try {
					$product_ids = array_slice($profile['products'], $offset, $limit);
					
					foreach ($product_ids as $product_id) {
						$product_id = intval($product_id);
						if ($product_id <= 0) continue;
						
						$prod = wc_get_product($product_id);
						if (!$prod || $prod->get_status() !== 'publish') {
							continue;
						}
						
						$entry = [
							'name' => html_entity_decode($prod->get_name(), ENT_QUOTES, 'UTF-8'),
							'price' => $prod->get_price(),
							'url' => get_permalink($prod->get_id()),
							'match' => 10
						];
						
						if (!empty($entry['name'])) {
							$out[] = $entry;
						}
					}
					
					return $out;
					
				} catch (Exception $e) {
					error_log('AIPC_Skin_Test::recommend_products - Fout bij ophalen specifieke producten: ' . $e->getMessage());
					return [];
				}
			}
		}
		
		return [];
	}
}