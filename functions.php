<?php

/**
 * Include Theme Customizer.
 *
 * @since v1.0
 */
$theme_customizer = __DIR__ . '/inc/customizer.php';
if ( is_readable( $theme_customizer ) ) {
	require_once $theme_customizer;
}

/**
 * F2F Dashboard - Funções para processamento de planilhas
 * @since v1.0
 */

// Ativar debug temporariamente
if (!defined('WP_DEBUG')) {
	define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
	define('WP_DEBUG_LOG', true);
}
if (!defined('WP_DEBUG_DISPLAY')) {
	define('WP_DEBUG_DISPLAY', true);
}

// Forçar criação da tabela na inicialização
add_action('init', 'f2f_ensure_table_exists');
function f2f_ensure_table_exists() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'f2f_spreadsheet_data';
	
	// Verificar se a tabela existe
	if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		f2f_create_spreadsheet_table();
		error_log('F2F Debug: Tabela criada - ' . $table_name);
	} else {
		error_log('F2F Debug: Tabela já existe - ' . $table_name);
	}
}

// Enqueue scripts e styles para o dashboard
if (!function_exists('f2f_dashboard_enqueue_scripts')) {
	function f2f_dashboard_enqueue_scripts() {
		// Carregar scripts em todas as páginas que usam o template Dashboard F2F
		// Verificação mais abrangente para garantir carregamento
		global $post;
		$load_scripts = false;
		
		if (is_page_template('page-dashboard.php') || 
			(is_page() && get_page_template_slug() == 'page-dashboard.php') ||
			(isset($post) && get_post_meta($post->ID, '_wp_page_template', true) == 'page-dashboard.php') ||
			(is_page() && strpos(get_the_content(), 'dashboard-container') !== false)) {
			$load_scripts = true;
		}
		
		if ($load_scripts) {
			// Log de debug
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('F2F Debug: Carregando scripts do dashboard');
			}
			
			// Chart.js
			wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', array(), '3.9.1', true);
			
			// DataTables
			wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js', array('jquery'), '1.11.5', true);
			wp_enqueue_script('datatables-bootstrap', 'https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js', array('datatables-js'), '1.11.5', true);
			wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css', array(), '1.11.5');
			
			// Font Awesome
			wp_enqueue_style('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css', array(), '6.0.0');
			
			// Dashboard custom styles
			wp_enqueue_style('f2f-dashboard-styles', get_template_directory_uri() . '/build/main.css', array(), '1.0.0');
			
			// Dashboard custom enhanced styles
			wp_enqueue_style('f2f-dashboard-custom', get_template_directory_uri() . '/assets/dashboard-custom.css', array('f2f-dashboard-styles'), '1.0.0');
		} else {
			// Log quando scripts não são carregados
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('F2F Debug: Scripts do dashboard NÃO carregados - condições não atendidas');
			}
		}
	}
	add_action('wp_enqueue_scripts', 'f2f_dashboard_enqueue_scripts');
}

// Criar tabela para armazenar dados da planilha
if (!function_exists('f2f_create_spreadsheet_table')) {
	function f2f_create_spreadsheet_table() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'f2f_spreadsheet_data';
		
		$charset_collate = $wpdb->get_charset_collate();
		
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			project_name varchar(255) NOT NULL DEFAULT '',
			task_name varchar(255) NOT NULL DEFAULT '',
			status varchar(50) NOT NULL DEFAULT 'Em Andamento',
			progress int(3) DEFAULT 0,
			assigned_to varchar(255) DEFAULT '',
			due_date date NULL,
			created_date datetime DEFAULT CURRENT_TIMESTAMP,
			updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			raw_data longtext,
			PRIMARY KEY (id)
		) $charset_collate;";
		
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('F2F Debug: SQL para criar tabela: ' . $sql);
		}
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
add_action('after_switch_theme', 'f2f_create_spreadsheet_table');
}

// Inserir dados de exemplo após criar a tabela
function f2f_init_sample_data() {
	f2f_insert_sample_data();
}
add_action('after_switch_theme', 'f2f_init_sample_data');
add_action('wp_loaded', 'f2f_init_sample_data');

// Processar upload de planilha
if (!function_exists('f2f_process_spreadsheet_upload')) {
	function f2f_process_spreadsheet_upload($file) {
		// Log de debug
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('F2F Debug: Iniciando upload de arquivo: ' . $file['name']);
		}
		
		// Verificar se houve erro no upload
		if ($file['error'] !== UPLOAD_ERR_OK) {
			return array('success' => false, 'message' => 'Erro no upload do arquivo: ' . $file['error']);
		}
		
		// Verificar tipo de arquivo
		$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		
		if (!in_array($file_extension, array('csv', 'xlsx', 'xls'))) {
			return array('success' => false, 'message' => 'Tipo de arquivo não suportado. Use CSV ou Excel (.xlsx, .xls).');
		}
		
		// Verificar tamanho do arquivo (máximo 10MB)
		if ($file['size'] > 10 * 1024 * 1024) {
			return array('success' => false, 'message' => 'Arquivo muito grande. Máximo 10MB.');
		}
		
		// Mover arquivo para diretório de uploads
		$upload_dir = wp_upload_dir();
		$target_file = $upload_dir['path'] . '/' . 'f2f_spreadsheet_' . time() . '.' . $file_extension;
		
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('F2F Debug: Movendo arquivo para: ' . $target_file);
		}
		
		if (!move_uploaded_file($file['tmp_name'], $target_file)) {
			return array('success' => false, 'message' => 'Erro ao salvar arquivo no servidor.');
		}
		
		// Processar arquivo baseado na extensão
		if ($file_extension === 'csv') {
			$result = f2f_process_csv_file($target_file);
		} else {
			$result = f2f_process_excel_file($target_file);
		}
		
		// Remover arquivo temporário
		if (file_exists($target_file)) {
			unlink($target_file);
		}
		
		return $result;
	}
}

// Processar arquivo CSV
if (!function_exists('f2f_process_csv_file')) {
	function f2f_process_csv_file($file_path) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'f2f_spreadsheet_data';
		$processed_rows = 0;
		$debug_info = '';
		
		// Log de debug inicial
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('F2F Debug: Iniciando processamento CSV: ' . $file_path);
			error_log('F2F Debug: Tabela de destino: ' . $table_name);
		}
		
		if (($handle = fopen($file_path, 'r')) !== FALSE) {
			// Ler cabeçalho
			$header = fgetcsv($handle, 1000, ',');
			
			// Detectar se é formato ClickUp
			$is_clickup_format = f2f_detect_clickup_format($header);
			
			// Informações de debug
			$debug_info = "Colunas encontradas: " . implode(', ', $header) . "\n";
			$debug_info .= "Formato ClickUp detectado: " . ($is_clickup_format ? 'SIM' : 'NÃO') . "\n";
			$debug_info .= "Total de colunas: " . count($header) . "\n";
			
			// Limpar dados existentes
			$wpdb->query("TRUNCATE TABLE $table_name");
			
			$row_count = 0;
			while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
				$row_count++;
				// Pular linhas vazias
				if (empty(array_filter($data))) {
					continue;
				}
				
				if (count($data) >= 2) { // Mínimo de 2 colunas (projeto e tarefa)
					if ($is_clickup_format) {
						$insert_data = f2f_map_clickup_data($header, $data);
					} else {
						// Formato genérico
						$insert_data = array(
							'project_name' => sanitize_text_field($data[0] ?? 'Projeto Sem Nome'),
							'task_name' => sanitize_text_field($data[1] ?? 'Tarefa Sem Nome'),
							'status' => sanitize_text_field($data[2] ?? 'Em Andamento'),
							'progress' => intval($data[3] ?? 0),
							'assigned_to' => sanitize_text_field($data[4] ?? ''),
							'due_date' => !empty($data[5]) ? date('Y-m-d', strtotime($data[5])) : null,
							'raw_data' => json_encode($data),
							'updated_date' => current_time('mysql')
						);
					}
					
					// Validar dados obrigatórios
					if (empty($insert_data['project_name']) || empty($insert_data['task_name'])) {
						if (defined('WP_DEBUG') && WP_DEBUG) {
							error_log('F2F Debug: Linha ' . $row_count . ' ignorada - dados obrigatórios vazios');
						}
						continue;
					}
					
					// Log da primeira linha para debug
					if ($row_count === 1) {
						$debug_info .= "Primeira linha mapeada:\n";
						$debug_info .= "Projeto: " . $insert_data['project_name'] . "\n";
						$debug_info .= "Tarefa: " . $insert_data['task_name'] . "\n";
						$debug_info .= "Status: " . $insert_data['status'] . "\n";
						$debug_info .= "Responsável: " . $insert_data['assigned_to'] . "\n";
					}
					
					// Debug: log dos dados antes da inserção
					if (defined('WP_DEBUG') && WP_DEBUG && $row_count <= 3) {
						error_log('F2F Debug: Tentando inserir linha ' . $row_count . ': ' . print_r($insert_data, true));
					}
					
					$insert_result = $wpdb->insert(
						$table_name,
						$insert_data,
						array('%s', '%s', '%s', '%d', '%s', '%s', '%s')
					);
					
					if ($insert_result !== false) {
						$processed_rows++;
					} else {
						if (defined('WP_DEBUG') && WP_DEBUG) {
							error_log('F2F Debug: Erro na inserção linha ' . $row_count . ': ' . $wpdb->last_error);
						}
					}
				}
			}
			fclose($handle);
			
			$debug_info .= "Total de linhas processadas: {$row_count}\n";
			$debug_info .= "Registros inseridos: {$processed_rows}\n";
			
			// Atualizar timestamp da última sincronização
			update_option('f2f_last_spreadsheet_update', current_time('mysql'));
			
			$format_msg = $is_clickup_format ? ' (formato ClickUp detectado)' : '';
			$result = array(
				'success' => true, 
				'message' => "Planilha processada com sucesso! {$processed_rows} registros importados{$format_msg}."
			);
			
			// Adicionar debug info se WP_DEBUG estiver ativo
			if (defined('WP_DEBUG') && WP_DEBUG) {
				$result['debug_info'] = $debug_info;
			}
			
			return $result;
		}
		
		return array('success' => false, 'message' => 'Erro ao ler arquivo CSV.');
	}
}

// Detectar formato ClickUp
if (!function_exists('f2f_detect_clickup_format')) {
	function f2f_detect_clickup_format($header) {
		// Colunas típicas do ClickUp (baseado nos dados fornecidos pelo usuário)
		$clickup_columns = array(
			'User ID', 'Username', 'Time Entry ID', 'Description', 'Billable',
			'Time Labels', 'Start', 'Start Text', 'Stop', 'Stop Text',
			'Time Tracked', 'Time Tracked Text', 'Space ID', 'Space Name',
			'Folder ID', 'Folder Name', 'List ID', 'List Name', 'Task ID', 'Task Name',
			'Task Status', 'Due Date', 'Due Date Text', 'Start Date', 'Start Date Text',
			'Task Time Estimated', 'Task Time Estimated Text', 'Task Time Spent', 'Task Time Spent Text',
			'User Total Time Estimated', 'User Total Time Estimated Text', 'User Total Time Tracked', 'User Total Time Tracked Text',
			'Tags', 'Checklists', 'User Period Time Spent', 'User Period Time Spent Text',
			'Date Created', 'Date Created Text', 'Custom Task ID', 'Parent Task ID',
			'Cliente', '⚠️ Obs de Status', 'Taskrow',
			// Colunas adicionais que podem aparecer
			'Status', 'Priority', 'Assignees', 'Date Updated', 'Points', 'Time Estimate', 'Custom Fields'
		);
		
		// Colunas obrigatórias que indicam definitivamente ClickUp
		$required_clickup_columns = array('User ID', 'Time Entry ID', 'Space ID', 'Task ID');
		
		// Verificar se tem pelo menos uma coluna obrigatória
		$has_required = false;
		foreach ($header as $column) {
			if (in_array(trim($column), $required_clickup_columns)) {
				$has_required = true;
				break;
			}
		}
		
		// Contar matches gerais
		$matches = 0;
		foreach ($header as $column) {
			if (in_array(trim($column), $clickup_columns)) {
				$matches++;
			}
		}
		
		// Retorna true se tem coluna obrigatória OU se tem pelo menos 25% de match
		return $has_required || ($matches >= count($header) * 0.25);
	}
}

// Mapear dados do ClickUp
if (!function_exists('f2f_map_clickup_data')) {
	function f2f_map_clickup_data($header, $data) {
		// Criar array associativo com cabeçalho
		$row_data = array();
		for ($i = 0; $i < count($header); $i++) {
			$row_data[trim($header[$i])] = $data[$i] ?? '';
		}
		
		// Log para debug (apenas em desenvolvimento)
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('ClickUp Row Data: ' . print_r($row_data, true));
		}
		
		// Mapear para estrutura do banco
		$project_name = '';
		// Priorizar Cliente se disponível, senão usar hierarquia Space > Folder > List
		if (!empty($row_data['Cliente'])) {
			$project_name = $row_data['Cliente'];
		} elseif (!empty($row_data['Space Name'])) {
			$project_name = $row_data['Space Name'];
		} elseif (!empty($row_data['Folder Name'])) {
			$project_name = $row_data['Folder Name'];
		} elseif (!empty($row_data['List Name'])) {
			$project_name = $row_data['List Name'];
		} else {
			$project_name = 'Projeto Sem Nome';
		}
		
		$task_name = $row_data['Task Name'] ?? $row_data['Description'] ?? 'Tarefa Sem Nome';
		
		// Determinar status baseado nos dados disponíveis
		$status = 'Em Andamento';
		if (!empty($row_data['Task Status'])) {
			// Usar Task Status se disponível (nova coluna)
			$status = $row_data['Task Status'];
		} elseif (!empty($row_data['Status'])) {
			// Usar status direto se disponível
			$status = $row_data['Status'];
		} elseif (!empty($row_data['Stop']) && !empty($row_data['Start'])) {
			$status = 'Concluído';
		} elseif (empty($row_data['Start'])) {
			$status = 'Pendente';
		}
		
		// Calcular progresso baseado no tempo rastreado
		$progress = 0;
		if (!empty($row_data['Time Tracked'])) {
			// Converter tempo para progresso (exemplo: 8 horas = 100%)
			$time_seconds = f2f_parse_time_to_seconds($row_data['Time Tracked']);
			if ($time_seconds > 0) {
				$progress = min(100, round(($time_seconds / (8 * 3600)) * 100));
			}
		} elseif (!empty($row_data['Task Time Spent']) && !empty($row_data['Task Time Estimated'])) {
			// Calcular progresso baseado em tempo gasto vs estimado
			$spent_seconds = f2f_parse_time_to_seconds($row_data['Task Time Spent']);
			$estimated_seconds = f2f_parse_time_to_seconds($row_data['Task Time Estimated']);
			if ($estimated_seconds > 0) {
				$progress = min(100, round(($spent_seconds / $estimated_seconds) * 100));
			}
		}
		
		$assigned_to = $row_data['Username'] ?? $row_data['Assignees'] ?? '';
		
		// Data de vencimento (priorizar Due Date se disponível)
		$due_date = null;
		if (!empty($row_data['Due Date'])) {
			$due_date = f2f_parse_clickup_date($row_data['Due Date']);
		} elseif (!empty($row_data['Due Date Text'])) {
			$due_date = f2f_parse_clickup_date($row_data['Due Date Text']);
		} elseif (!empty($row_data['Stop'])) {
			$due_date = f2f_parse_clickup_date($row_data['Stop']);
		}
		
		return array(
			'project_name' => sanitize_text_field($project_name),
			'task_name' => sanitize_text_field($task_name),
			'status' => sanitize_text_field($status),
			'progress' => intval($progress),
			'assigned_to' => sanitize_text_field($assigned_to),
			'due_date' => $due_date,
			'raw_data' => json_encode($row_data)
		);
	}
}

// Converter datas do ClickUp
if (!function_exists('f2f_parse_clickup_date')) {
	function f2f_parse_clickup_date($date_string) {
		if (empty($date_string)) {
			return null;
		}
		
		// Se for um timestamp (número grande), converter diretamente
		if (is_numeric($date_string) && strlen($date_string) >= 10) {
			// Timestamp em milissegundos (ClickUp usa isso)
			if (strlen($date_string) > 10) {
				$timestamp = intval($date_string) / 1000;
			} else {
				$timestamp = intval($date_string);
			}
			return date('Y-m-d', $timestamp);
		}
		
		// Tentar diferentes formatos de data
		$formats = array(
			'Y-m-d H:i:s',
			'Y-m-d',
			'm/d/Y H:i:s',
			'm/d/Y',
			'd/m/Y H:i:s',
			'd/m/Y',
			'Y-m-d\TH:i:s\Z',
			'Y-m-d\TH:i:s.u\Z',
			'm/d/Y, g:i:s A O', // Formato: 10/04/2024, 4:00:00 AM -03
			'm/d/Y, g:i A O'    // Formato: 10/04/2024, 4:00 AM -03
		);
		
		foreach ($formats as $format) {
			$date = DateTime::createFromFormat($format, $date_string);
			if ($date !== false) {
				return $date->format('Y-m-d');
			}
		}
		
		// Fallback para strtotime
		$timestamp = strtotime($date_string);
		if ($timestamp !== false) {
			return date('Y-m-d', $timestamp);
		}
		
		return null;
	}
}

// Converter tempo do ClickUp para segundos
if (!function_exists('f2f_parse_time_to_seconds')) {
	function f2f_parse_time_to_seconds($time_string) {
		// Formato típico: "1:30:45" ou "90045" (segundos)
		if (is_numeric($time_string)) {
			return intval($time_string);
		}
		
		// Formato HH:MM:SS
		if (preg_match('/^(\d+):(\d+):(\d+)$/', $time_string, $matches)) {
			return ($matches[1] * 3600) + ($matches[2] * 60) + $matches[3];
		}
		
		// Formato MM:SS
		if (preg_match('/^(\d+):(\d+)$/', $time_string, $matches)) {
			return ($matches[1] * 60) + $matches[2];
		}
		
		return 0;
	}
}

// Processar arquivo Excel usando SimpleXLSX
if (!function_exists('f2f_process_excel_file')) {
	function f2f_process_excel_file($file_path) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'f2f_spreadsheet_data';
		
		$debug_info = '';
		
		try {
			// Incluir a biblioteca SimpleXLSX
			if (!class_exists('SimpleXLSX')) {
				require_once get_template_directory() . '/inc/SimpleXLSX.php';
			}
			
			// Verificar se o arquivo existe
			if (!file_exists($file_path)) {
				return array('success' => false, 'message' => 'Arquivo não encontrado.');
			}
			
			// Tentar abrir o arquivo Excel
			if ($xlsx = SimpleXLSX::parse($file_path)) {
				$rows = $xlsx->rows();
				
				if (empty($rows)) {
					return array('success' => false, 'message' => 'Arquivo Excel vazio.');
				}
				
				// Primeira linha como cabeçalho
				$header = array_shift($rows);
				
				// Debug: informações sobre as colunas
				if (defined('WP_DEBUG') && WP_DEBUG) {
					$debug_info .= "Colunas encontradas: " . implode(', ', $header) . "\n";
					$debug_info .= "Total de colunas: " . count($header) . "\n";
				}
				
				// Detectar se é formato ClickUp
				$is_clickup = f2f_detect_clickup_format($header);
				
				if (defined('WP_DEBUG') && WP_DEBUG) {
					$debug_info .= "Formato ClickUp detectado: " . ($is_clickup ? 'Sim' : 'Não') . "\n";
				}
				
				// Limpar dados existentes
				$wpdb->query("TRUNCATE TABLE $table_name");
				
				$inserted_count = 0;
				$total_rows = count($rows);
				
				// Processar cada linha
				foreach ($rows as $index => $row) {
					// Pular linhas vazias
					if (empty(array_filter($row))) {
						continue;
					}
					
					if ($is_clickup) {
						// Mapear dados do ClickUp
						$mapped_data = f2f_map_clickup_data($header, $row);
						
						// Debug: primeira linha mapeada
						if ($index === 0 && defined('WP_DEBUG') && WP_DEBUG) {
							$debug_info .= "Primeira linha mapeada:\n";
							$debug_info .= "  Projeto: " . $mapped_data['project_name'] . "\n";
							$debug_info .= "  Tarefa: " . $mapped_data['task_name'] . "\n";
							$debug_info .= "  Status: " . $mapped_data['status'] . "\n";
							$debug_info .= "  Responsável: " . $mapped_data['assigned_to'] . "\n";
						}
					} else {
						// Processamento genérico para outros formatos
						$mapped_data = array(
							'project_name' => sanitize_text_field($row[0] ?? 'Projeto'),
							'task_name' => sanitize_text_field($row[1] ?? 'Tarefa'),
							'status' => sanitize_text_field($row[2] ?? 'Em Andamento'),
							'progress' => intval($row[3] ?? 0),
							'assigned_to' => sanitize_text_field($row[4] ?? ''),
							'due_date' => !empty($row[5]) ? date('Y-m-d', strtotime($row[5])) : null,
							'raw_data' => json_encode($row)
						);
					}
					
					// Debug: log dos dados antes da inserção
					if (defined('WP_DEBUG') && WP_DEBUG && $index <= 2) {
						$debug_info .= "Tentando inserir linha " . ($index + 1) . ":\n";
						$debug_info .= "  Dados: " . print_r($mapped_data, true) . "\n";
					}
					
					// Inserir no banco de dados
					$result = $wpdb->insert(
						$table_name,
						array(
							'project_name' => $mapped_data['project_name'],
							'task_name' => $mapped_data['task_name'],
							'status' => $mapped_data['status'],
							'progress' => $mapped_data['progress'],
							'assigned_to' => $mapped_data['assigned_to'],
							'due_date' => $mapped_data['due_date'],
							'raw_data' => $mapped_data['raw_data'],
							'updated_date' => current_time('mysql')
						),
						array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
					);
					
					if ($result !== false) {
						$inserted_count++;
					} else {
						if (defined('WP_DEBUG') && WP_DEBUG) {
							$debug_info .= "Erro na inserção linha " . ($index + 1) . ": " . $wpdb->last_error . "\n";
						}
					}
				}
				
				// Atualizar timestamp da última sincronização
				update_option('f2f_last_spreadsheet_update', current_time('mysql'));
				
				if (defined('WP_DEBUG') && WP_DEBUG) {
					$debug_info .= "Total de linhas processadas: $total_rows\n";
					$debug_info .= "Registros inseridos: $inserted_count\n";
				}
				
				$message = "Arquivo Excel processado com sucesso! $inserted_count registros importados.";
				
				return array(
					'success' => true, 
					'message' => $message,
					'debug_info' => $debug_info
				);
				
			} else {
				return array('success' => false, 'message' => 'Erro ao processar arquivo Excel: ' . SimpleXLSX::parseError());
			}
			
		} catch (Exception $e) {
			return array('success' => false, 'message' => 'Erro ao processar arquivo Excel: ' . $e->getMessage());
		}
	}
}

// Funções para obter estatísticas
if (!function_exists('f2f_get_total_records')) {
	function f2f_get_total_records() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'f2f_spreadsheet_data';
		return $wpdb->get_var("SELECT COUNT(*) FROM $table_name") ?: 0;
	}
}

if (!function_exists('f2f_get_active_projects')) {
	function f2f_get_active_projects() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'f2f_spreadsheet_data';
		return $wpdb->get_var("SELECT COUNT(DISTINCT project_name) FROM $table_name WHERE status != 'Concluído'") ?: 0;
	}
}

if (!function_exists('f2f_get_completed_tasks')) {
	function f2f_get_completed_tasks() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'f2f_spreadsheet_data';
		return $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'Concluído'") ?: 0;
	}
}

if (!function_exists('f2f_get_last_update')) {
	function f2f_get_last_update() {
		$last_update = get_option('f2f_last_spreadsheet_update');
		return $last_update ? date('d/m/Y H:i', strtotime($last_update)) : 'Nunca';
	}
}

// Renderizar cabeçalho da tabela
if (!function_exists('f2f_render_table_header')) {
	function f2f_render_table_header() {
		return '<tr>
			<th>Projeto</th>
			<th>Tarefa</th>
			<th>Status</th>
			<th>Progresso</th>
			<th>Responsável</th>
			<th>Prazo</th>
			<th>Atualizado</th>
		</tr>';
	}
}

// Renderizar dados da tabela
if (!function_exists('f2f_render_table_data')) {
	function f2f_render_table_data() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'f2f_spreadsheet_data';
		
		$results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY updated_date DESC LIMIT 100");
		
		if (empty($results)) {
			return '<tr><td colspan="7" class="text-center">Nenhum dado encontrado. Faça upload de uma planilha.</td></tr>';
		}
		
		$output = '';
		foreach ($results as $row) {
			$status_class = f2f_get_status_class($row->status);
			$progress_color = f2f_get_progress_color($row->progress);
			
			$output .= '<tr>';
			$output .= '<td>' . esc_html($row->project_name) . '</td>';
			$output .= '<td>' . esc_html($row->task_name) . '</td>';
			$output .= '<td><span class="badge ' . $status_class . '">' . esc_html($row->status) . '</span></td>';
			$output .= '<td><div class="progress"><div class="progress-bar ' . $progress_color . '" style="width: ' . $row->progress . '%">' . $row->progress . '%</div></div></td>';
			$output .= '<td>' . esc_html($row->assigned_to) . '</td>';
			$output .= '<td>' . ($row->due_date ? date('d/m/Y', strtotime($row->due_date)) : '-') . '</td>';
			$output .= '<td>' . date('d/m/Y H:i', strtotime($row->updated_date)) . '</td>';
			$output .= '</tr>';
		}
		
		return $output;
	}
}

// Obter classe CSS para status
if (!function_exists('f2f_get_status_class')) {
	function f2f_get_status_class($status) {
		switch (strtolower($status)) {
			case 'concluído':
			case 'concluido':
			case 'done':
				return 'bg-success';
			case 'em andamento':
			case 'in progress':
				return 'bg-primary';
			case 'pendente':
			case 'pending':
				return 'bg-warning';
			case 'cancelado':
			case 'cancelled':
				return 'bg-danger';
			default:
				return 'bg-secondary';
		}
	}
}

// Obter cor da barra de progresso
if (!function_exists('f2f_get_progress_color')) {
	function f2f_get_progress_color($progress) {
		if ($progress >= 80) return 'bg-success';
		if ($progress >= 60) return 'bg-info';
		if ($progress >= 40) return 'bg-warning';
		return 'bg-danger';
	}
}

if (!function_exists('f2f_get_project_options')) {
	function f2f_get_project_options() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'f2f_spreadsheet_data';
		
		$projects = $wpdb->get_results(
			"SELECT DISTINCT project_name FROM $table_name WHERE project_name IS NOT NULL AND project_name != '' ORDER BY project_name ASC"
		);
		
		$options = '';
		foreach ($projects as $project) {
			$options .= '<option value="' . esc_attr($project->project_name) . '">' . esc_html($project->project_name) . '</option>';
		}
		
		return $options;
	}
}

if (!function_exists('f2f_get_assigned_options')) {
	function f2f_get_assigned_options() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'f2f_spreadsheet_data';
		
		$assigned = $wpdb->get_results(
			"SELECT DISTINCT assigned_to FROM $table_name WHERE assigned_to IS NOT NULL AND assigned_to != '' ORDER BY assigned_to ASC"
		);
		
		$options = '';
		foreach ($assigned as $person) {
			$options .= '<option value="' . esc_attr($person->assigned_to) . '">' . esc_html($person->assigned_to) . '</option>';
		}
		
		return $options;
	}
}

if (!function_exists('f2f_insert_sample_data')) {
	function f2f_insert_sample_data() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'f2f_spreadsheet_data';
		
		// Verificar se já existem dados
		$existing_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
		if ($existing_count > 0) {
			return; // Já existem dados, não inserir exemplos
		}
		
		// Dados de exemplo
		$sample_data = array(
			array(
				'project_name' => 'Website Corporativo',
				'task_name' => 'Desenvolvimento da página inicial',
				'status' => 'Em Andamento',
				'progress' => 75,
				'assigned_to' => 'João Silva',
				'due_date' => date('Y-m-d', strtotime('+5 days')),
				'raw_data' => json_encode(array('priority' => 'Alta', 'department' => 'TI'))
			),
			array(
				'project_name' => 'Website Corporativo',
				'task_name' => 'Design do layout responsivo',
				'status' => 'Concluído',
				'progress' => 100,
				'assigned_to' => 'Maria Santos',
				'due_date' => date('Y-m-d', strtotime('-2 days')),
				'raw_data' => json_encode(array('priority' => 'Média', 'department' => 'Design'))
			),
			array(
				'project_name' => 'App Mobile',
				'task_name' => 'Implementação do login',
				'status' => 'Aguardando',
				'progress' => 30,
				'assigned_to' => 'Pedro Costa',
				'due_date' => date('Y-m-d', strtotime('+10 days')),
				'raw_data' => json_encode(array('priority' => 'Alta', 'department' => 'Mobile'))
			),
			array(
				'project_name' => 'App Mobile',
				'task_name' => 'Testes de usabilidade',
				'status' => 'Atrasado',
				'progress' => 10,
				'assigned_to' => 'Ana Oliveira',
				'due_date' => date('Y-m-d', strtotime('-1 days')),
				'raw_data' => json_encode(array('priority' => 'Baixa', 'department' => 'QA'))
			),
			array(
				'project_name' => 'Sistema ERP',
				'task_name' => 'Módulo de vendas',
				'status' => 'Em Andamento',
				'progress' => 60,
				'assigned_to' => 'Carlos Lima',
				'due_date' => date('Y-m-d', strtotime('+15 days')),
				'raw_data' => json_encode(array('priority' => 'Alta', 'department' => 'Backend'))
			),
			array(
				'project_name' => 'Sistema ERP',
				'task_name' => 'Integração com API externa',
				'status' => 'Pausado',
				'progress' => 25,
				'assigned_to' => 'João Silva',
				'due_date' => date('Y-m-d', strtotime('+20 days')),
				'raw_data' => json_encode(array('priority' => 'Média', 'department' => 'Backend'))
			)
		);
		
		// Inserir dados de exemplo
		foreach ($sample_data as $data) {
			$wpdb->insert(
				$table_name,
				$data,
				array('%s', '%s', '%s', '%d', '%s', '%s', '%s')
			);
		}
		
		// Atualizar timestamp da última sincronização
		update_option('f2f_last_spreadsheet_update', current_time('mysql'));
	}
	add_action('after_switch_theme', 'f2f_insert_sample_data');
}

if ( ! function_exists( 'f2f_dashboard_setup_theme' ) ) {
	/**
	 * General Theme Settings.
	 *
	 * @since v1.0
	 *
	 * @return void
	 */
	function f2f_dashboard_setup_theme() {
		// Make theme available for translation: Translations can be filed in the /languages/ directory.
		load_theme_textdomain( 'f2f-dashboard', __DIR__ . '/languages' );

		/**
		 * Set the content width based on the theme's design and stylesheet.
		 *
		 * @since v1.0
		 */
		global $content_width;
		if ( ! isset( $content_width ) ) {
			$content_width = 800;
		}

		// Theme Support.
		add_theme_support( 'title-tag' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support(
			'html5',
			array(
				'search-form',
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
				'script',
				'style',
				'navigation-widgets',
			)
		);

		// Add support for Block Styles.
		add_theme_support( 'wp-block-styles' );
		// Add support for full and wide alignment.
		add_theme_support( 'align-wide' );
		// Add support for Editor Styles.
		add_theme_support( 'editor-styles' );
		// Enqueue Editor Styles.
		add_editor_style( 'style-editor.css' );

		// Default attachment display settings.
		update_option( 'image_default_align', 'none' );
		update_option( 'image_default_link_type', 'none' );
		update_option( 'image_default_size', 'large' );

		// Custom CSS styles of WorPress gallery.
		add_filter( 'use_default_gallery_style', '__return_false' );
	}
	add_action( 'after_setup_theme', 'f2f_dashboard_setup_theme' );

	/**
	 * Enqueue editor stylesheet (for iframed Post Editor):
	 * https://make.wordpress.org/core/2023/07/18/miscellaneous-editor-changes-in-wordpress-6-3/#post-editor-iframed
	 *
	 * @since v3.5.1
	 *
	 * @return void
	 */
	function f2f_dashboard_load_editor_styles() {
		if ( is_admin() ) {
			wp_enqueue_style( 'editor-style', get_theme_file_uri( 'style-editor.css' ) );
		}
	}
	add_action( 'enqueue_block_assets', 'f2f_dashboard_load_editor_styles' );

	// Disable Block Directory: https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/filters/editor-filters.md#block-directory
	remove_action( 'enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets' );
	remove_action( 'enqueue_block_editor_assets', 'gutenberg_enqueue_block_editor_assets_block_directory' );
}

if ( ! function_exists( 'wp_body_open' ) ) {
	/**
	 * Fire the wp_body_open action.
	 *
	 * Added for backwards compatibility to support pre 5.2.0 WordPress versions.
	 *
	 * @since v2.2
	 *
	 * @return void
	 */
	function wp_body_open() {
		do_action( 'wp_body_open' );
	}
}

if ( ! function_exists( 'f2f_dashboard_add_user_fields' ) ) {
	/**
	 * Add new User fields to Userprofile:
	 * get_user_meta( $user->ID, 'facebook_profile', true );
	 *
	 * @since v1.0
	 *
	 * @param array $fields User fields.
	 *
	 * @return array
	 */
	function f2f_dashboard_add_user_fields( $fields ) {
		// Add new fields.
		$fields['facebook_profile'] = 'Facebook URL';
		$fields['twitter_profile']  = 'Twitter URL';
		$fields['linkedin_profile'] = 'LinkedIn URL';
		$fields['xing_profile']     = 'Xing URL';
		$fields['github_profile']   = 'GitHub URL';

		return $fields;
	}
	add_filter( 'user_contactmethods', 'f2f_dashboard_add_user_fields' );
}

/**
 * Test if a page is a blog page.
 * if ( is_blog() ) { ... }
 *
 * @since v1.0
 *
 * @global WP_Post $post Global post object.
 *
 * @return bool
 */
function is_blog() {
	global $post;
	$posttype = get_post_type( $post );

	return ( ( is_archive() || is_author() || is_category() || is_home() || is_single() || ( is_tag() && ( 'post' === $posttype ) ) ) ? true : false );
}

/**
 * Disable comments for Media (Image-Post, Jetpack-Carousel, etc.)
 *
 * @since v1.0
 *
 * @param bool $open    Comments open/closed.
 * @param int  $post_id Post ID.
 *
 * @return bool
 */
function f2f_dashboard_filter_media_comment_status( $open, $post_id = null ) {
	$media_post = get_post( $post_id );

	if ( 'attachment' === $media_post->post_type ) {
		return false;
	}

	return $open;
}
add_filter( 'comments_open', 'f2f_dashboard_filter_media_comment_status', 10, 2 );

/**
 * Style Edit buttons as badges: https://getbootstrap.com/docs/5.0/components/badge
 *
 * @since v1.0
 *
 * @param string $link Post Edit Link.
 *
 * @return string
 */
function f2f_dashboard_custom_edit_post_link( $link ) {
	return str_replace( 'class="post-edit-link"', 'class="post-edit-link badge bg-secondary"', $link );
}
add_filter( 'edit_post_link', 'f2f_dashboard_custom_edit_post_link' );

/**
 * Style Edit buttons as badges: https://getbootstrap.com/docs/5.0/components/badge
 *
 * @since v1.0
 *
 * @param string $link Comment Edit Link.
 */
function f2f_dashboard_custom_edit_comment_link( $link ) {
	return str_replace( 'class="comment-edit-link"', 'class="comment-edit-link badge bg-secondary"', $link );
}
add_filter( 'edit_comment_link', 'f2f_dashboard_custom_edit_comment_link' );

/**
 * Responsive oEmbed filter: https://getbootstrap.com/docs/5.0/helpers/ratio
 *
 * @since v1.0
 *
 * @param string $html Inner HTML.
 *
 * @return string
 */
function f2f_dashboard_oembed_filter( $html ) {
	return '<div class="ratio ratio-16x9">' . $html . '</div>';
}
add_filter( 'embed_oembed_html', 'f2f_dashboard_oembed_filter', 10 );

if ( ! function_exists( 'f2f_dashboard_content_nav' ) ) {
	/**
	 * Display a navigation to next/previous pages when applicable.
	 *
	 * @since v1.0
	 *
	 * @param string $nav_id Navigation ID.
	 */
	function f2f_dashboard_content_nav( $nav_id ) {
		global $wp_query;

		if ( $wp_query->max_num_pages > 1 ) {
			?>
			<div id="<?php echo esc_attr( $nav_id ); ?>" class="d-flex mb-4 justify-content-between">
				<div><?php next_posts_link( '<span aria-hidden="true">&larr;</span> ' . esc_html__( 'Older posts', 'f2f-dashboard' ) ); ?></div>
				<div><?php previous_posts_link( esc_html__( 'Newer posts', 'f2f-dashboard' ) . ' <span aria-hidden="true">&rarr;</span>' ); ?></div>
			</div><!-- /.d-flex -->
			<?php
		} else {
			echo '<div class="clearfix"></div>';
		}
	}

	/**
	 * Add Class.
	 *
	 * @since v1.0
	 *
	 * @return string
	 */
	function posts_link_attributes() {
		return 'class="btn btn-secondary btn-lg"';
	}
	add_filter( 'next_posts_link_attributes', 'posts_link_attributes' );
	add_filter( 'previous_posts_link_attributes', 'posts_link_attributes' );
}

/**
 * Init Widget areas in Sidebar.
 *
 * @since v1.0
 *
 * @return void
 */
function f2f_dashboard_widgets_init() {
	// Area 1.
	register_sidebar(
		array(
			'name'          => 'Primary Widget Area (Sidebar)',
			'id'            => 'primary_widget_area',
			'before_widget' => '',
			'after_widget'  => '',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);

	// Area 2.
	register_sidebar(
		array(
			'name'          => 'Secondary Widget Area (Header Navigation)',
			'id'            => 'secondary_widget_area',
			'before_widget' => '',
			'after_widget'  => '',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);

	// Area 3.
	register_sidebar(
		array(
			'name'          => 'Third Widget Area (Footer)',
			'id'            => 'third_widget_area',
			'before_widget' => '',
			'after_widget'  => '',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);
}
add_action( 'widgets_init', 'f2f_dashboard_widgets_init' );

if ( ! function_exists( 'f2f_dashboard_article_posted_on' ) ) {
	/**
	 * "Theme posted on" pattern.
	 *
	 * @since v1.0
	 */
	function f2f_dashboard_article_posted_on() {
		printf(
			wp_kses_post( __( '<span class="sep">Posted on </span><a href="%1$s" title="%2$s" rel="bookmark"><time class="entry-date" datetime="%3$s">%4$s</time></a><span class="by-author"> <span class="sep"> by </span> <span class="author-meta vcard"><a class="url fn n" href="%5$s" title="%6$s" rel="author">%7$s</a></span></span>', 'f2f-dashboard' ) ),
			esc_url( get_permalink() ),
			esc_attr( get_the_date() . ' - ' . get_the_time() ),
			esc_attr( get_the_date( 'c' ) ),
			esc_html( get_the_date() . ' - ' . get_the_time() ),
			esc_url( get_author_posts_url( (int) get_the_author_meta( 'ID' ) ) ),
			sprintf( esc_attr__( 'View all posts by %s', 'f2f-dashboard' ), get_the_author() ),
			get_the_author()
		);
	}
}

/**
 * Template for Password protected post form.
 *
 * @since v1.0
 *
 * @global WP_Post $post Global post object.
 *
 * @return string
 */
function f2f_dashboard_password_form() {
	global $post;
	$label = 'pwbox-' . ( empty( $post->ID ) ? wp_rand() : $post->ID );

	$output                  = '<div class="row">';
		$output             .= '<form action="' . esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) ) . '" method="post">';
		$output             .= '<h4 class="col-md-12 alert alert-warning">' . esc_html__( 'This content is password protected. To view it please enter your password below.', 'f2f-dashboard' ) . '</h4>';
			$output         .= '<div class="col-md-6">';
				$output     .= '<div class="input-group">';
					$output .= '<input type="password" name="post_password" id="' . esc_attr( $label ) . '" placeholder="' . esc_attr__( 'Password', 'f2f-dashboard' ) . '" class="form-control" />';
					$output .= '<div class="input-group-append"><input type="submit" name="submit" class="btn btn-primary" value="' . esc_attr__( 'Submit', 'f2f-dashboard' ) . '" /></div>';
				$output     .= '</div><!-- /.input-group -->';
			$output         .= '</div><!-- /.col -->';
		$output             .= '</form>';
	$output                 .= '</div><!-- /.row -->';

	return $output;
}
add_filter( 'the_password_form', 'f2f_dashboard_password_form' );


if ( ! function_exists( 'f2f_dashboard_comment' ) ) {
	/**
	 * Style Reply link.
	 *
	 * @since v1.0
	 *
	 * @param string $link Link output.
	 *
	 * @return string
	 */
	function f2f_dashboard_replace_reply_link_class( $link ) {
		return str_replace( "class='comment-reply-link", "class='comment-reply-link btn btn-outline-secondary", $link );
	}
	add_filter( 'comment_reply_link', 'f2f_dashboard_replace_reply_link_class' );

	/**
	 * Template for comments and pingbacks:
	 * add function to comments.php ... wp_list_comments( array( 'callback' => 'f2f_dashboard_comment' ) );
	 *
	 * @since v1.0
	 *
	 * @param object $comment Comment object.
	 * @param array  $args    Comment args.
	 * @param int    $depth   Comment depth.
	 */
	function f2f_dashboard_comment( $comment, $args, $depth ) {
		$GLOBALS['comment'] = $comment;
		switch ( $comment->comment_type ) :
			case 'pingback':
			case 'trackback':
				?>
		<li class="post pingback">
			<p>
				<?php
					esc_html_e( 'Pingback:', 'f2f-dashboard' );
					comment_author_link();
					edit_comment_link( esc_html__( 'Edit', 'f2f-dashboard' ), '<span class="edit-link">', '</span>' );
				?>
			</p>
				<?php
				break;
			default:
				?>
		<li <?php comment_class(); ?> id="li-comment-<?php comment_ID(); ?>">
			<article id="comment-<?php comment_ID(); ?>" class="comment">
				<footer class="comment-meta">
					<div class="comment-author vcard">
						<?php
							$avatar_size = ( '0' !== $comment->comment_parent ? 68 : 136 );
							echo get_avatar( $comment, $avatar_size );

							/* Translators: 1: Comment author, 2: Date and time */
							printf(
								wp_kses_post( __( '%1$s, %2$s', 'f2f-dashboard' ) ),
								sprintf( '<span class="fn">%s</span>', get_comment_author_link() ),
								sprintf(
									'<a href="%1$s"><time datetime="%2$s">%3$s</time></a>',
									esc_url( get_comment_link( $comment->comment_ID ) ),
									get_comment_time( 'c' ),
									/* Translators: 1: Date, 2: Time */
									sprintf( esc_html__( '%1$s ago', 'f2f-dashboard' ), human_time_diff( (int) get_comment_time( 'U' ), current_time( 'timestamp' ) ) )
								)
							);

							edit_comment_link( esc_html__( 'Edit', 'f2f-dashboard' ), '<span class="edit-link">', '</span>' );
						?>
					</div><!-- .comment-author .vcard -->

					<?php if ( '0' === $comment->comment_approved ) { ?>
						<em class="comment-awaiting-moderation">
							<?php esc_html_e( 'Your comment is awaiting moderation.', 'f2f-dashboard' ); ?>
						</em>
						<br />
					<?php } ?>
				</footer>

				<div class="comment-content"><?php comment_text(); ?></div>

				<div class="reply">
					<?php
						comment_reply_link(
							array_merge(
								$args,
								array(
									'reply_text' => esc_html__( 'Reply', 'f2f-dashboard' ) . ' <span>&darr;</span>',
									'depth'      => $depth,
									'max_depth'  => $args['max_depth'],
								)
							)
						);
					?>
				</div><!-- /.reply -->
			</article><!-- /#comment-## -->
				<?php
				break;
		endswitch;
	}

	/**
	 * Custom Comment form.
	 *
	 * @since v1.0
	 * @since v1.1: Added 'submit_button' and 'submit_field'
	 * @since v2.0.2: Added '$consent' and 'cookies'
	 *
	 * @param array $args    Form args.
	 * @param int   $post_id Post ID.
	 *
	 * @return array
	 */
	function f2f_dashboard_custom_commentform( $args = array(), $post_id = null ) {
		if ( null === $post_id ) {
			$post_id = get_the_ID();
		}

		$commenter     = wp_get_current_commenter();
		$user          = wp_get_current_user();
		$user_identity = $user->exists() ? $user->display_name : '';

		$args = wp_parse_args( $args );

		$req      = get_option( 'require_name_email' );
		$aria_req = ( $req ? " aria-required='true' required" : '' );
		$consent  = ( empty( $commenter['comment_author_email'] ) ? '' : ' checked="checked"' );
		$fields   = array(
			'author'  => '<div class="form-floating mb-3">
							<input type="text" id="author" name="author" class="form-control" value="' . esc_attr( $commenter['comment_author'] ) . '" placeholder="' . esc_html__( 'Name', 'f2f-dashboard' ) . ( $req ? '*' : '' ) . '"' . $aria_req . ' />
							<label for="author">' . esc_html__( 'Name', 'f2f-dashboard' ) . ( $req ? '*' : '' ) . '</label>
						</div>',
			'email'   => '<div class="form-floating mb-3">
							<input type="email" id="email" name="email" class="form-control" value="' . esc_attr( $commenter['comment_author_email'] ) . '" placeholder="' . esc_html__( 'Email', 'f2f-dashboard' ) . ( $req ? '*' : '' ) . '"' . $aria_req . ' />
							<label for="email">' . esc_html__( 'Email', 'f2f-dashboard' ) . ( $req ? '*' : '' ) . '</label>
						</div>',
			'url'     => '',
			'cookies' => '<p class="form-check mb-3 comment-form-cookies-consent">
							<input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" class="form-check-input" type="checkbox" value="yes"' . $consent . ' />
							<label class="form-check-label" for="wp-comment-cookies-consent">' . esc_html__( 'Save my name, email, and website in this browser for the next time I comment.', 'f2f-dashboard' ) . '</label>
						</p>',
		);

		$defaults = array(
			'fields'               => apply_filters( 'comment_form_default_fields', $fields ),
			'comment_field'        => '<div class="form-floating mb-3">
											<textarea id="comment" name="comment" class="form-control" aria-required="true" required placeholder="' . esc_attr__( 'Comment', 'f2f-dashboard' ) . ( $req ? '*' : '' ) . '"></textarea>
											<label for="comment">' . esc_html__( 'Comment', 'f2f-dashboard' ) . '</label>
										</div>',
			/** This filter is documented in wp-includes/link-template.php */
			'must_log_in'          => '<p class="must-log-in">' . sprintf( wp_kses_post( __( 'You must be <a href="%s">logged in</a> to post a comment.', 'f2f-dashboard' ) ), wp_login_url( esc_url( get_permalink( get_the_ID() ) ) ) ) . '</p>',
			/** This filter is documented in wp-includes/link-template.php */
			'logged_in_as'         => '<p class="logged-in-as">' . sprintf( wp_kses_post( __( 'Logged in as <a href="%1$s">%2$s</a>. <a href="%3$s" title="Log out of this account">Log out?</a>', 'f2f-dashboard' ) ), get_edit_user_link(), $user->display_name, wp_logout_url( apply_filters( 'the_permalink', esc_url( get_permalink( get_the_ID() ) ) ) ) ) . '</p>',
			'comment_notes_before' => '<p class="small comment-notes">' . esc_html__( 'Your Email address will not be published.', 'f2f-dashboard' ) . '</p>',
			'comment_notes_after'  => '',
			'id_form'              => 'commentform',
			'id_submit'            => 'submit',
			'class_submit'         => 'btn btn-primary',
			'name_submit'          => 'submit',
			'title_reply'          => '',
			'title_reply_to'       => esc_html__( 'Leave a Reply to %s', 'f2f-dashboard' ),
			'cancel_reply_link'    => esc_html__( 'Cancel reply', 'f2f-dashboard' ),
			'label_submit'         => esc_html__( 'Post Comment', 'f2f-dashboard' ),
			'submit_button'        => '<input type="submit" id="%2$s" name="%1$s" class="%3$s" value="%4$s" />',
			'submit_field'         => '<div class="form-submit">%1$s %2$s</div>',
			'format'               => 'html5',
		);

		return $defaults;
	}
	add_filter( 'comment_form_defaults', 'f2f_dashboard_custom_commentform' );
}

if ( function_exists( 'register_nav_menus' ) ) {
	/**
	 * Nav menus.
	 *
	 * @since v1.0
	 *
	 * @return void
	 */
	register_nav_menus(
		array(
			'main-menu'   => 'Main Navigation Menu',
			'footer-menu' => 'Footer Menu',
		)
	);
}

// Custom Nav Walker: wp_bootstrap_navwalker().
$custom_walker = __DIR__ . '/inc/wp-bootstrap-navwalker.php';
if ( is_readable( $custom_walker ) ) {
	require_once $custom_walker;
}

$custom_walker_footer = __DIR__ . '/inc/wp-bootstrap-navwalker-footer.php';
if ( is_readable( $custom_walker_footer ) ) {
	require_once $custom_walker_footer;
}

/**
 * Loading All CSS Stylesheets and Javascript Files.
 *
 * @since v1.0
 *
 * @return void
 */
function f2f_dashboard_scripts_loader() {
	$theme_version = wp_get_theme()->get( 'Version' );

	// 1. Styles.
	wp_enqueue_style( 'style', get_theme_file_uri( 'style.css' ), array(), $theme_version, 'all' );
	wp_enqueue_style( 'main', get_theme_file_uri( 'build/main.css' ), array(), $theme_version, 'all' ); // main.scss: Compiled Framework source + custom styles.

	if ( is_rtl() ) {
		wp_enqueue_style( 'rtl', get_theme_file_uri( 'build/rtl.css' ), array(), $theme_version, 'all' );
	}

	// 2. Scripts.
	wp_enqueue_script( 'mainjs', get_theme_file_uri( 'build/main.js' ), array(), $theme_version, true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'f2f_dashboard_scripts_loader' );

/**
 * F2F Dashboard Enhancements
 */
if (!function_exists('f2f_dashboard_enhancements')) {
    function f2f_dashboard_enhancements() {
        // Enqueue Chart.js for dashboard
        if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'f2f-dashboard') {
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        }
        
        // Add dashboard styles
        wp_enqueue_style('f2f-dashboard-admin', get_template_directory_uri() . '/assets/admin-dashboard.css', array(), '1.0.0');
    }
    add_action('admin_enqueue_scripts', 'f2f_dashboard_enhancements');
}

/**
 * Custom Dashboard Widgets for WordPress Admin
 */
if (!function_exists('f2f_add_dashboard_widgets')) {
    function f2f_add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'f2f_clickup_summary',
            'Resumo ClickUp',
            'f2f_clickup_summary_widget'
        );
        
        wp_add_dashboard_widget(
            'f2f_project_status',
            'Status dos Projetos',
            'f2f_project_status_widget'
        );
    }
    add_action('wp_dashboard_setup', 'f2f_add_dashboard_widgets');
}

function f2f_clickup_summary_widget() {
    $total_tasks = wp_count_posts('clickup_task');
    $total_projects = wp_count_posts('project_data');
    $last_sync = get_option('f2f_last_sync', 'Nunca');
    
    echo '<div class="f2f-widget-summary">';
    echo '<div class="summary-item"><strong>' . $total_tasks->publish . '</strong><br>Tarefas Totais</div>';
    echo '<div class="summary-item"><strong>' . $total_projects->publish . '</strong><br>Projetos Ativos</div>';
    echo '<div class="summary-item"><strong>' . $last_sync . '</strong><br>Última Sincronização</div>';
    echo '</div>';
    
    echo '<p><a href="' . admin_url('admin.php?page=f2f-dashboard') . '" class="button button-primary">Ver Dashboard Completo</a></p>';
}

function f2f_project_status_widget() {
    $projects = get_posts(array(
        'post_type' => 'project_data',
        'numberposts' => 5,
        'post_status' => 'publish'
    ));
    
    if (empty($projects)) {
        echo '<p>Nenhum projeto encontrado. <a href="' . admin_url('admin.php?page=f2f-clickup-settings') . '">Configure a integração ClickUp</a></p>';
        return;
    }
    
    echo '<ul class="f2f-project-list">';
    foreach ($projects as $project) {
        $space_data = json_decode(get_post_meta($project->ID, 'space_data', true), true);
        $task_count = get_posts(array(
            'post_type' => 'clickup_task',
            'meta_key' => 'project_id',
            'meta_value' => $project->ID,
            'numberposts' => -1
        ));
        
        echo '<li>';
        echo '<strong>' . $project->post_title . '</strong><br>';
        echo 'Tarefas: ' . count($task_count) . '<br>';
        echo '<small>Atualizado: ' . get_post_meta($project->ID, 'last_sync', true) . '</small>';
        echo '</li>';
    }
    echo '</ul>';
}

/**
 * AJAX Handlers for Dashboard
 */
add_action('wp_ajax_get_chart_data', 'f2f_get_chart_data');
function f2f_get_chart_data() {
    $projects = get_posts(array(
        'post_type' => 'project_data',
        'numberposts' => -1,
        'post_status' => 'publish'
    ));
    
    $labels = array();
    $data = array();
    
    foreach ($projects as $project) {
        $labels[] = $project->post_title;
        
        $task_count = get_posts(array(
            'post_type' => 'clickup_task',
            'meta_key' => 'project_id',
            'meta_value' => $project->ID,
            'numberposts' => -1
        ));
        
        $data[] = count($task_count);
    }
    
    $chart_data = array(
        'labels' => $labels,
        'datasets' => array(
            array(
                'label' => 'Número de Tarefas',
                'data' => $data,
                'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                'borderColor' => 'rgba(54, 162, 235, 1)',
                'borderWidth' => 1
            )
        )
    );
    
    wp_send_json($chart_data);
}

add_action('wp_ajax_test_clickup_connection', 'f2f_test_clickup_connection');
function f2f_test_clickup_connection() {
    $api_token = get_option('f2f_clickup_api_token');
    $team_id = get_option('f2f_clickup_team_id');
    
    if (empty($api_token) || empty($team_id)) {
        wp_send_json_error('API Token ou Team ID não configurados');
    }
    
    $response = wp_remote_get("https://api.clickup.com/api/v2/team/{$team_id}", array(
        'headers' => array(
            'Authorization' => $api_token,
            'Content-Type' => 'application/json'
        )
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    
    if ($status_code === 200) {
        wp_send_json_success('Conexão estabelecida com sucesso!');
    } else {
        wp_send_json_error('Erro na conexão. Código: ' . $status_code);
    }
}
