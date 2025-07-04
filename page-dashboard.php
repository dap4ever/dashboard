<?php
/**
 * Template Name: Dashboard F2F
 * Description: Dashboard para visualização de dados de planilhas
 */

get_header();

// Verificar se o usuário tem permissão
if (!current_user_can('manage_options')) {
    wp_die('Você não tem permissão para acessar esta página.');
}

// Processar upload de planilha
if (isset($_POST['upload_spreadsheet']) && isset($_FILES['spreadsheet_file'])) {
    $upload_result = f2f_process_spreadsheet_upload($_FILES['spreadsheet_file']);
}
?>

<div class="container-fluid dashboard-container">
    <div class="row">
        <div class="col-12">
            <h1 class="dashboard-title">Dashboard F2F</h1>
            
            <?php if (isset($upload_result)): ?>
                <div class="alert <?php echo $upload_result['success'] ? 'alert-success' : 'alert-danger'; ?>">
                    <?php echo $upload_result['message']; ?>
                    <?php if (isset($upload_result['debug_info']) && defined('WP_DEBUG') && WP_DEBUG): ?>
                        <details class="mt-2">
                            <summary>Informações de Debug</summary>
                            <pre class="mt-2"><?php echo esc_html($upload_result['debug_info']); ?></pre>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Upload de Planilha -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3>Upload de Planilha</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <h6><i class="fas fa-info-circle"></i> Como exportar do ClickUp:</h6>
                        <ol class="mb-0">
                            <li>No ClickUp, vá para <strong>Time Tracking</strong> → <strong>Reports</strong></li>
                            <li>Selecione o período desejado e os projetos</li>
                            <li>Clique em <strong>Export</strong> → <strong>CSV</strong> ou <strong>Excel</strong></li>
                            <li>Faça o upload do arquivo aqui</li>
                        </ol>
                        <small class="text-muted">O sistema detecta automaticamente o formato ClickUp e mapeia as colunas corretamente. Suporta arquivos CSV e XLSX exportados do ClickUp Time Tracking.</small>
                    </div>
                    <form method="post" enctype="multipart/form-data" class="upload-form">
                        <div class="row">
                            <div class="col-md-8">
                                <input type="file" class="form-control" id="spreadsheet_file" name="spreadsheet_file" accept=".csv,.xlsx,.xls" required>
                                <small class="form-text text-muted">Formatos aceitos: CSV, Excel (.xlsx, .xls) | <strong>Recomendado:</strong> CSV do ClickUp Time Tracking</small>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" name="upload_spreadsheet" class="btn btn-primary btn-block">
                                    <i class="fas fa-upload"></i> Fazer Upload
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Estatísticas Gerais -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h2 class="stat-number"><?php echo f2f_get_total_records(); ?></h2>
                            <p class="stat-label">Total de Registros</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h2 class="stat-number"><?php echo f2f_get_active_projects(); ?></h2>
                            <p class="stat-label">Projetos Ativos</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h2 class="stat-number"><?php echo f2f_get_completed_tasks(); ?></h2>
                            <p class="stat-label">Tarefas Concluídas</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h2 class="stat-number"><?php echo f2f_get_last_update(); ?></h2>
                            <p class="stat-label">Última Atualização</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráficos -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Progresso dos Projetos</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="projectProgressChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Status das Tarefas</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="taskStatusChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4><i class="fas fa-filter"></i> Filtros</h4>
                </div>
                <div class="card-body">
                    <form id="filterForm" class="row g-3">
                        <div class="col-md-3">
                            <label for="filterProject" class="form-label">Projeto</label>
                            <select class="form-select" id="filterProject">
                                <option value="">Todos os Projetos</option>
                                <?php echo f2f_get_project_options(); ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filterStatus" class="form-label">Status</label>
                            <select class="form-select" id="filterStatus">
                                <option value="">Todos os Status</option>
                                <option value="Em Andamento">Em Andamento</option>
                                <option value="Concluído">Concluído</option>
                                <option value="Aguardando">Aguardando</option>
                                <option value="Atrasado">Atrasado</option>
                                <option value="Pausado">Pausado</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filterAssigned" class="form-label">Responsável</label>
                            <select class="form-select" id="filterAssigned">
                                <option value="">Todos os Responsáveis</option>
                                <?php echo f2f_get_assigned_options(); ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filterDateRange" class="form-label">Período</label>
                            <select class="form-select" id="filterDateRange">
                                <option value="">Todos os Períodos</option>
                                <option value="today">Hoje</option>
                                <option value="week">Esta Semana</option>
                                <option value="month">Este Mês</option>
                                <option value="quarter">Este Trimestre</option>
                                <option value="year">Este Ano</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-primary" onclick="applyFilters()">
                                    <i class="fas fa-search"></i> Aplicar Filtros
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                                    <i class="fas fa-times"></i> Limpar Filtros
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Tabela de Dados -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Dados da Planilha</h4>
                    <div class="table-controls">
                        <button class="btn btn-sm btn-outline-primary" onclick="exportData('csv')">Exportar CSV</button>
                        <button class="btn btn-sm btn-outline-success" onclick="exportData('excel')">Exportar Excel</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <?php 
                        $total_records = f2f_get_total_records();
                        if ($total_records > 0): 
                        ?>
                        <div class="mb-3">
                            <small class="text-muted">Mostrando até 100 registros mais recentes de <?php echo $total_records; ?> total</small>
                        </div>
                        <table id="dataTable" class="table table-striped table-hover">
                            <thead class="table-dark">
                                <?php echo f2f_render_table_header(); ?>
                            </thead>
                            <tbody>
                                <?php echo f2f_render_table_data(); ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-file-csv fa-3x text-muted mb-3"></i>
                            <h5>Nenhum dado encontrado</h5>
                            <p class="text-muted">Faça o upload de uma planilha do ClickUp para começar a visualizar os dados.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Inicializar gráficos quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    initializeDataTable();
});

function initializeCharts() {
    // Gráfico de Progresso dos Projetos
    const projectCtx = document.getElementById('projectProgressChart').getContext('2d');
    fetch('<?php echo admin_url('admin-ajax.php?action=get_project_progress_data'); ?>')
        .then(response => response.json())
        .then(data => {
            new Chart(projectCtx, {
                type: 'doughnut',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
    
    // Gráfico de Status das Tarefas
    const taskCtx = document.getElementById('taskStatusChart').getContext('2d');
    fetch('<?php echo admin_url('admin-ajax.php?action=get_task_status_data'); ?>')
        .then(response => response.json())
        .then(data => {
            new Chart(taskCtx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
}

let dataTable;

function initializeDataTable() {
    // Verificar se jQuery e DataTables estão disponíveis
    if (typeof $ === 'undefined') {
        console.error('F2F Debug: jQuery não está carregado');
        return;
    }
    
    if (typeof $.fn.DataTable === 'undefined') {
        console.error('F2F Debug: DataTables não está carregado');
        return;
    }
    
    // Verificar se a tabela existe
    if ($('#dataTable').length === 0) {
        console.warn('F2F Debug: Tabela #dataTable não encontrada');
        return;
    }
    
    try {
        console.log('F2F Debug: Inicializando DataTable');
        dataTable = $('#dataTable').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
            },
            columnDefs: [
                { targets: [0], title: 'Projeto' },
                { targets: [1], title: 'Tarefa' },
                { targets: [2], title: 'Status' },
                { targets: [3], title: 'Progresso' },
                { targets: [4], title: 'Responsável' },
                { targets: [5], title: 'Data de Entrega' }
            ]
        });
        console.log('F2F Debug: DataTable inicializado com sucesso');
    } catch (error) {
        console.error('F2F Debug: Erro ao inicializar DataTable:', error);
    }
}

function applyFilters() {
    console.log('F2F Debug: Aplicando filtros');
    
    const project = document.getElementById('filterProject')?.value || '';
    const status = document.getElementById('filterStatus')?.value || '';
    const assigned = document.getElementById('filterAssigned')?.value || '';
    const dateRange = document.getElementById('filterDateRange')?.value || '';
    
    console.log('F2F Debug: Valores dos filtros:', { project, status, assigned, dateRange });
    
    // Verificar se DataTable está inicializado
    if (!dataTable) {
        console.error('F2F Debug: DataTable não está inicializado');
        return;
    }
    
    try {
        // Limpar filtros customizados anteriores
        while ($.fn.dataTable.ext.search.length > 0) {
            $.fn.dataTable.ext.search.pop();
        }
        
        // Filtro por projeto (coluna 0) - busca parcial
        if (project && project !== '') {
            dataTable.column(0).search(project, false, false);
        } else {
            dataTable.column(0).search('');
        }
        
        // Filtro por status (coluna 2) - busca parcial
        if (status && status !== '') {
            dataTable.column(2).search(status, false, false);
        } else {
            dataTable.column(2).search('');
        }
        
        // Filtro por responsável (coluna 4) - busca parcial
        if (assigned && assigned !== '') {
            dataTable.column(4).search(assigned, false, false);
        } else {
            dataTable.column(4).search('');
        }
        
        // Aplicar filtro de data se necessário
        if (dateRange && dateRange !== '') {
            applyDateFilter(dateRange);
        } else {
            dataTable.column(5).search('');
        }
        
        // Redesenhar a tabela
        dataTable.draw();
        
        // Atualizar estatísticas
        updateFilteredStats();
        
        console.log('F2F Debug: Filtros aplicados com sucesso');
    } catch (error) {
        console.error('F2F Debug: Erro ao aplicar filtros:', error);
    }
}

function clearFilters() {
    console.log('F2F Debug: Limpando filtros');
    
    try {
        // Limpar todos os campos de filtro
        const filterProject = document.getElementById('filterProject');
        const filterStatus = document.getElementById('filterStatus');
        const filterAssigned = document.getElementById('filterAssigned');
        const filterDateRange = document.getElementById('filterDateRange');
        
        if (filterProject) filterProject.value = '';
        if (filterStatus) filterStatus.value = '';
        if (filterAssigned) filterAssigned.value = '';
        if (filterDateRange) filterDateRange.value = '';
        
        // Verificar se DataTable está inicializado
        if (!dataTable) {
            console.error('F2F Debug: DataTable não está inicializado para limpeza');
            return;
        }
        
        // Limpar filtros do DataTable
        // Limpar filtros customizados
        while ($.fn.dataTable.ext.search.length > 0) {
            $.fn.dataTable.ext.search.pop();
        }
        
        // Limpar todos os filtros de coluna
        dataTable.search('').columns().search('').draw();
        
        // Restaurar estatísticas originais
        updateFilteredStats();
        
        console.log('F2F Debug: Filtros limpos com sucesso');
    } catch (error) {
        console.error('F2F Debug: Erro ao limpar filtros:', error);
    }
}

function applyDateFilter(range) {
    const today = new Date();
    let startDate, endDate;
    
    switch(range) {
        case 'today':
            startDate = endDate = today.toISOString().split('T')[0];
            break;
        case 'week':
            const weekStart = new Date(today);
            weekStart.setDate(today.getDate() - today.getDay());
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekStart.getDate() + 6);
            startDate = weekStart.toISOString().split('T')[0];
            endDate = weekEnd.toISOString().split('T')[0];
            break;
        case 'month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
            break;
        case 'quarter':
            const quarter = Math.floor(today.getMonth() / 3);
            startDate = new Date(today.getFullYear(), quarter * 3, 1).toISOString().split('T')[0];
            endDate = new Date(today.getFullYear(), quarter * 3 + 3, 0).toISOString().split('T')[0];
            break;
        case 'year':
            startDate = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
            endDate = new Date(today.getFullYear(), 11, 31).toISOString().split('T')[0];
            break;
        default:
            return;
    }
    
    // Aplicar filtro customizado de data
    $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
            const dateStr = data[5]; // Coluna de data
            if (!dateStr) return true;
            
            // Extrair apenas a data da string (formato YYYY-MM-DD)
            const dateMatch = dateStr.match(/\d{4}-\d{2}-\d{2}/);
            if (!dateMatch) return true;
            
            const rowDate = dateMatch[0];
            return rowDate >= startDate && rowDate <= endDate;
        }
    );
}

function updateFilteredStats() {
    if (!dataTable) return;
    
    // Contar registros filtrados
    const filteredData = dataTable.rows({ filter: 'applied' }).data();
    const totalFiltered = filteredData.length;
    
    // Contar por status
    const statusCounts = {};
    const projectCounts = {};
    
    for (let i = 0; i < filteredData.length; i++) {
        const row = filteredData[i];
        const status = $(row[2]).text() || row[2]; // Extrair texto do badge
        const project = row[0];
        
        statusCounts[status] = (statusCounts[status] || 0) + 1;
        projectCounts[project] = (projectCounts[project] || 0) + 1;
    }
    
    // Atualizar cards de estatísticas
    updateStatCard(0, totalFiltered, 'Registros Filtrados');
    updateStatCard(1, Object.keys(projectCounts).length, 'Projetos');
    updateStatCard(2, statusCounts['Concluído'] || 0, 'Concluídas');
    
    // Atualizar gráficos com dados filtrados
    updateChartsWithFilteredData(statusCounts, projectCounts);
}

function updateStatCard(index, value, label) {
    const cards = document.querySelectorAll('.stat-card');
    if (cards[index]) {
        const numberEl = cards[index].querySelector('.stat-number');
        const labelEl = cards[index].querySelector('.stat-label');
        if (numberEl) numberEl.textContent = value;
        if (labelEl && label) labelEl.textContent = label;
    }
}

function updateChartsWithFilteredData(statusCounts, projectCounts) {
    // Atualizar gráfico de status das tarefas
    if (window.taskChart) {
        const labels = Object.keys(statusCounts);
        const data = Object.values(statusCounts);
        
        window.taskChart.data.labels = labels;
        window.taskChart.data.datasets[0].data = data;
        window.taskChart.update();
    }
    
    // Atualizar gráfico de progresso dos projetos
    if (window.projectChart) {
        const labels = Object.keys(projectCounts);
        const data = Object.values(projectCounts);
        
        window.projectChart.data.labels = labels;
        window.projectChart.data.datasets[0].data = data;
        window.projectChart.update();
    }
}

function exportData(format) {
    // Obter dados filtrados
    const filteredData = dataTable ? dataTable.rows({ filter: 'applied' }).data().toArray() : [];
    
    // Criar parâmetros de filtro para o export
    const filters = {
        project: document.getElementById('filterProject').value,
        status: document.getElementById('filterStatus').value,
        assigned: document.getElementById('filterAssigned').value,
        dateRange: document.getElementById('filterDateRange').value
    };
    
    const params = new URLSearchParams({
        action: 'export_spreadsheet_data',
        format: format,
        filters: JSON.stringify(filters)
    });
    
    window.location.href = '<?php echo admin_url('admin-ajax.php'); ?>?' + params.toString();
}
</script>

<?php get_footer(); ?>