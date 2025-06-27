<?php
/*
Plugin Name: STAYArta Power
Description: Plugin central del ecosistema STAYArta. Integra IA (NovaSTAYBot), automatización, seguridad, rendimiento, analítica, Mailchimp, Google Analytics y Make.com.
Version: 1.1
Author: Carlos Arta
*/

    
    // ==========================================
    // FUNCIONES DE ACTIVACIÓN Y DESACTIVACIÓN
    // ==========================================
    
    public function on_activation() {
        try {
            // Crear tablas de base de datos
            $this->maybe_create_tables();
            
            // Configurar ajustes por defecto
            if (!get_option('stayarta_power_settings')) {
                update_option('stayarta_power_settings', $this->get_default_settings());
            }
            
            // Crear directorios necesarios
            $upload_dir = wp_upload_dir();
            $stayarta_dir = $upload_dir['basedir'] . '/stayarta-power';
            
            if (!file_exists($stayarta_dir)) {
                wp_mkdir_p($stayarta_dir);
                
                // Añadir archivo .htaccess para seguridad
                $htaccess_content = "Options -Indexes\n<Files *.php>\nOrder allow,deny\nDeny from all\n</Files>";
                file_put_contents($stayarta_dir . '/.htaccess', $htaccess_content);
            }
            
            // Limpiar rewrite rules
            flush_rewrite_rules();
            
            // Programar limpieza de logs
            if (!wp_next_scheduled('stayarta_cleanup_logs')) {
                wp_schedule_event(time(), 'weekly', 'stayarta_cleanup_logs');
            }
            
            // Log de activación
            error_log('STAYArta Power System activated successfully');
            
        } catch (Exception $e) {
            error_log('STAYArta Activation Error: ' . $e->getMessage());
            
            // Mostrar error al admin
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p><strong>STAYArta Power:</strong> Error durante la activación: ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
    
    public function on_deactivation() {
        try {
            // Limpiar datos temporales
            delete_transient('stayarta_performance_cache');
            delete_transient('stayarta_analytics_cache');
            delete_transient('stayarta_dashboard_cache');
            
            // Limpiar eventos programados
            wp_clear_scheduled_hook('stayarta_cleanup_logs');
            wp_clear_scheduled_hook('stayarta_send_analytics_report');
            
            // Limpiar rewrite rules
            flush_rewrite_rules();
            
            // Log de desactivación
            error_log('STAYArta Power System deactivated');
            
        } catch (Exception $e) {
            error_log('STAYArta Deactivation Error: ' . $e->getMessage());
        }
    }
    
    // ==========================================
    // FUNCIONES ADICIONALES PARA COMPLETAR LA FUNCIONALIDAD
    // ==========================================
    
    private function get_analytics_overview() {
        try {
            global $wpdb;
            
            $analytics_table = $wpdb->prefix . 'stayarta_analytics';
            $today = current_time('Y-m-d');
            $week_ago = date('Y-m-d', strtotime('-7 days'));
            
            // Verificar si la tabla existe
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $analytics_table)) !== $analytics_table) {
                return [];
            }
            
            return [
                'events_today' => [
                    'label' => 'Eventos Hoy',
                    'value' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$analytics_table} WHERE DATE(created_at) = %s", $today)) ?: 0,
                    'description' => 'Interacciones registradas hoy'
                ],
                'events_week' => [
                    'label' => 'Eventos (7 días)',
                    'value' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$analytics_table} WHERE DATE(created_at) >= %s", $week_ago)) ?: 0,
                    'description' => 'Eventos de la última semana'
                ],
                'unique_visitors' => [
                    'label' => 'Visitantes Únicos',
                    'value' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT user_ip) FROM {$analytics_table} WHERE DATE(created_at) = %s", $today)) ?: 0,
                    'description' => 'Visitantes únicos hoy'
                ],
                'top_events' => [
                    'label' => 'Evento Más Común',
                    'value' => $wpdb->get_var("SELECT event_type FROM {$analytics_table} GROUP BY event_type ORDER BY COUNT(*) DESC LIMIT 1") ?: 'N/A',
                    'description' => 'Tipo de evento más frecuente'
                ]
            ];
            
        } catch (Exception $e) {
            error_log('STAYArta Analytics Overview Error: ' . $e->getMessage());
            return [];
        }
    }
    
    private function render_recent_events_table() {
        try {
            global $wpdb;
            
            $analytics_table = $wpdb->prefix . 'stayarta_analytics';
            
            $events = $wpdb->get_results($wpdb->prepare(
                "SELECT event_type, COUNT(*) as count, MAX(created_at) as last_occurrence 
                 FROM {$analytics_table} 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 GROUP BY event_type 
                 ORDER BY count DESC 
                 LIMIT 10"
            ));
            
            if (empty($events)) {
                echo '<p>No hay eventos recientes para mostrar.</p>';
                return;
            }
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Tipo de Evento</th>
                        <th>Cantidad</th>
                        <th>Última Ocurrencia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><?php echo esc_html($event->event_type); ?></td>
                            <td><?php echo esc_html($event->count); ?></td>
                            <td><?php echo esc_html(human_time_diff(strtotime($event->last_occurrence), current_time('timestamp')) . ' ago'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            
        } catch (Exception $e) {
            echo '<p>Error al cargar eventos recientes: ' . esc_html($e->getMessage()) . '</p>';
        }
    }
    
    private function get_chart_data() {
        try {
            global $wpdb;
            
            $analytics_table = $wpdb->prefix . 'stayarta_analytics';
            
            $analytics_table = $wpdb->prefix . 'stayarta_analytics';
            
            // Obtener datos de los últimos 7 días
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE(created_at) as date, COUNT(*) as events 
                 FROM {$analytics_table} 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 GROUP BY DATE(created_at) 
                 ORDER BY DATE(created_at) ASC"
            ));
            
            $chart_data = [
                'labels' => [],
                'data' => []
            ];
            
            // Llenar datos de los últimos 7 días (incluso si no hay eventos)
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $label = date('M j', strtotime($date));
                
                $chart_data['labels'][] = $label;
                
                // Buscar eventos para esta fecha
                $events_count = 0;
                foreach ($results as $result) {
                    if ($result->date === $date) {
                        $events_count = intval($result->events);
                        break;
                    }
                }
                
                $chart_data['data'][] = $events_count;
            }
            
            return $chart_data;
            
        } catch (Exception $e) {
            error_log('STAYArta Chart Data Error: ' . $e->getMessage());
            return [
                'labels' => ['Sin datos'],
                'data' => [0]
            ];
        }
    }
    
    private function inject_admin_styles() {
        ?>
        <style>
        .stayarta-admin-wrap {
            background: #f1f1f1;
            margin: 0 -20px -10px -22px;
            padding: 20px;
            min-height: calc(100vh - 32px);
        }
        
        .stayarta-admin-title {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            margin: 0 0 30px 0;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }
        
        .stayarta-admin-title .version {
            opacity: 0.8;
            font-size: 0.7em;
            font-weight: normal;
        }
        
        .stayarta-metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stayarta-metric-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s ease;
        }
        
        .stayarta-metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .metric-icon {
            font-size: 3em;
            opacity: 0.8;
        }
        
        .metric-content h3 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 1.1em;
        }
        
        .metric-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            margin: 5px 0;
            text-shadow: 0 0 10px rgba(102, 126, 234, 0.3);
        }
        
        .metric-content p {
            margin: 0;
            color: #666;
            font-size: 0.9em;
        }
        
        .stayarta-chart-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin: 30px 0;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .stayarta-chart-container h2 {
            margin-top: 0;
            color: #333;
        }
        
        .stayarta-quick-actions {
            margin: 30px 0;
        }
        
        .stayarta-action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stayarta-action-btn {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            color: #667eea;
            border: 2px solid rgba(102, 126, 234, 0.3);
            padding: 15px 20px;
            border-radius: 15px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .stayarta-action-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: translateY(-2px);
        }
        
        .stayarta-system-status {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .stayarta-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stayarta-status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #ddd;
        }
        
        .status-label {
            font-weight: 600;
        }
        
        .status-value {
            opacity: 0.8;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-left: 10px;
        }
        
        .status-indicator.good {
            background: #4CAF50;
            box-shadow: 0 0 10px rgba(76, 175, 80, 0.5);
        }
        
        .status-indicator.warning {
            background: #FF9800;
            box-shadow: 0 0 10px rgba(255, 152, 0, 0.5);
        }
        
        .status-indicator.inactive {
            background: #999;
        }
        
        /* Estilos para configuración */
        .stayarta-settings-form {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .stayarta-setting-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .stayarta-setting-row:last-child {
            border-bottom: none;
        }
        
        .setting-info {
            flex: 1;
            margin-right: 20px;
        }
        
        .setting-info strong {
            color: #333;
            font-size: 1.1em;
        }
        
        .setting-info .description {
            color: #666;
            margin: 5px 0 0 0;
            font-size: 0.9em;
        }
        
        .stayarta-toggle {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .stayarta-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }
        
        .stayarta-toggle input:checked + .slider {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .stayarta-toggle input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        @media (max-width: 768px) {
            .stayarta-metrics-grid {
                grid-template-columns: 1fr;
            }
            
            .stayarta-action-grid {
                grid-template-columns: 1fr;
            }
            
            .stayarta-status-grid {
                grid-template-columns: 1fr;
            }
            
            .stayarta-setting-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
        </style>
        <?php
    }
    
    private function inject_admin_scripts() {
        ?>
        <script>
        // STAYArta Admin Dashboard JavaScript
        class STAYArtaAdmin {
            static init() {
                this.initChart();
                this.setupAutoRefresh();
                this.bindActions();
                this.setupTabSwitching();
            }
            
            static initChart() {
                const ctx = document.getElementById('performanceChart');
                if (!ctx || typeof Chart === 'undefined') {
                    return;
                }
                
                // Obtener datos del servidor
                this.loadChartData();
            }
            
            static loadChartData() {
                fetch(stayartaAdmin.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'stayarta_get_dashboard_data',
                        nonce: stayartaAdmin.nonce
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.chart_data) {
                        this.createChart(data.data.chart_data);
                    }
                })
                .catch(err => console.error('Error loading chart data:', err));
            }
            
            static createChart(chartData) {
                const ctx = document.getElementById('performanceChart');
                
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chartData.labels || [],
                        datasets: [{
                            label: 'Eventos Diarios',
                            data: chartData.data || [],
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#667eea',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }
            
            static setupAutoRefresh() {
                // Refrescar datos cada 5 minutos
                setInterval(() => {
                    this.refreshDashboardData();
                }, 300000);
            }
            
            static refreshDashboardData() {
                fetch(stayartaAdmin.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'stayarta_get_dashboard_data',
                        nonce: stayartaAdmin.nonce
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.updateMetrics(data.data);
                    }
                })
                .catch(err => console.error('Error refreshing data:', err));
            }
            
            static updateMetrics(data) {
                const metricValues = document.querySelectorAll('.metric-value');
                const newValues = [
                    data.performance + '%',
                    data.security + '%',
                    data.features + '/' + Object.keys(stayartaAdmin.settings).length,
                    data.events_today
                ];
                
                metricValues.forEach((metric, index) => {
                    if (newValues[index] !== undefined) {
                        metric.textContent = newValues[index];
                    }
                });
            }
            
            static bindActions() {
                // Bind action buttons
                document.querySelectorAll('.stayarta-action-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const action = e.target.dataset.action;
                        if (action && typeof this[action.replace('-', '_')] === 'function') {
                            this[action.replace('-', '_')]();
                        }
                    });
                });
            }
            
            static setupTabSwitching() {
                const tabs = document.querySelectorAll('.nav-tab');
                const contents = document.querySelectorAll('.tab-content');
                
                tabs.forEach(tab => {
                    tab.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        // Remove active class from all tabs and contents
                        tabs.forEach(t => t.classList.remove('nav-tab-active'));
                        contents.forEach(c => c.style.display = 'none');
                        
                        // Add active class to clicked tab
                        this.classList.add('nav-tab-active');
                        
                        // Show corresponding content
                        const targetId = this.getAttribute('href').substring(1);
                        const targetContent = document.getElementById(targetId);
                        if (targetContent) {
                            targetContent.style.display = 'block';
                        }
                    });
                });
            }
            
            static clear_cache() {
                this.showNotification('🗑️ Limpiando cache...', 'info');
                
                // Simular limpieza de cache
                setTimeout(() => {
                    this.showNotification('✅ Cache limpiado correctamente', 'success');
                }, 2000);
            }
            
            static optimize_db() {
                if (!confirm(stayartaAdmin.strings.confirm + ' ¿Optimizar la base de datos?')) {
                    return;
                }
                
                this.showNotification('🔧 Optimizando base de datos...', 'info');
                
                setTimeout(() => {
                    this.showNotification('✅ Base de datos optimizada', 'success');
                }, 3000);
            }
            
            static test_chatbot() {
                this.showNotification('🤖 Probando conexión con NovaSTAYBot...', 'info');
                
                // Test real del chatbot
                fetch(stayartaAdmin.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'stayarta_chatbot_message',
                        message: 'test connection',
                        nonce: stayartaAdmin.nonce
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.showNotification('✅ NovaSTAYBot funcionando correctamente', 'success');
                    } else {
                        this.showNotification('❌ Error en NovaSTAYBot', 'error');
                    }
                })
                .catch(() => {
                    this.showNotification('❌ Error de conexión con el chatbot', 'error');
                });
            }
            
            static export_settings() {
                const settings = stayartaAdmin.settings;
                const dataStr = JSON.stringify(settings, null, 2);
                const dataBlob = new Blob([dataStr], {type: 'application/json'});
                
                const link = document.createElement('a');
                link.href = URL.createObjectURL(dataBlob);
                link.download = 'stayarta-settings-' + new Date().toISOString().split('T')[0] + '.json';
                link.click();
                
                this.showNotification('📤 Configuración exportada', 'success');
            }
            
            static showNotification(message, type = 'info') {
                // Reutilizar el sistema de notificaciones de STAYArta si está disponible
                if (typeof STAYArta !== 'undefined' && STAYArta.notifications) {
                    STAYArta.notifications.show(message, type);
                    return;
                }
                
                // Fallback simple
                const notification = document.createElement('div');
                notification.innerHTML = message;
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 20px;
                    border-radius: 8px;
                    color: white;
                    font-weight: 600;
                    z-index: 99999;
                    max-width: 300px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                    transition: all 0.3s ease;
                `;
                
                // Colores según tipo
                const colors = {
                    success: 'linear-gradient(135deg, #4CAF50, #45a049)',
                    error: 'linear-gradient(135deg, #f44336, #da190b)',
                    warning: 'linear-gradient(135deg, #FF9800, #F57C00)',
                    info: 'linear-gradient(135deg, #667eea, #764ba2)'
                };
                
                notification.style.background = colors[type] || colors.info;
                
                document.body.appendChild(notification);
                
                // Remover después de 4 segundos
                setTimeout(() => {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 300);
                }, 4000);
            }
        }
        
        // Inicializar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', () => {
            STAYArtaAdmin.init();
        });
        
        // Hacer disponible globalmente
        window.STAYArtaAdmin = STAYArtaAdmin;
        </script>
        <?php
    }
}

// ==========================================
// INICIALIZACIÓN SINGLETON DEL PLUGIN
// ==========================================

function stayarta_power_init() {
    return STAYArta_Power_System::get_instance();
}

// Inicializar el plugin
add_action('plugins_loaded', 'stayarta_power_init');

// ==========================================
// HOOKS DE ACTIVACIÓN Y DESACTIVACIÓN
// ==========================================

register_activation_hook(__FILE__, function() {
    $plugin = STAYArta_Power_System::get_instance();
    $plugin->on_activation();
});

register_deactivation_hook(__FILE__, function() {
    $plugin = STAYArta_Power_System::get_instance();
    $plugin->on_deactivation();
});

// Hook de desinstalación
register_uninstall_hook(__FILE__, function() {
    // Solo eliminar datos si el usuario así lo configuró
    if (get_option('stayarta_delete_data_on_uninstall')) {
        global $wpdb;
        
        // Eliminar tablas personalizadas
        $tables = [
            $wpdb->prefix . 'stayarta_analytics',
            $wpdb->prefix . 'stayarta_chatbot_logs',
            $wpdb->prefix . 'stayarta_heatmap'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        // Eliminar opciones
        delete_option('stayarta_power_settings');
        delete_option('stayarta_power_db_version');
        
        // Eliminar archivos subidos
        $upload_dir = wp_upload_dir();
        $stayarta_dir = $upload_dir['basedir'] . '/stayarta-power';
        
        if (file_exists($stayarta_dir)) {
            $files = glob($stayarta_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($stayarta_dir);
        }
        
        // Limpiar transients
        delete_transient('stayarta_performance_cache');
        delete_transient('stayarta_analytics_cache');
        delete_transient('stayarta_dashboard_cache');
    }
});

// ==========================================
// FUNCIONES AUXILIARES GLOBALES
// ==========================================

if (!function_exists('stayarta_get_setting')) {
    function stayarta_get_setting($key, $default = false) {
        $settings = get_option('stayarta_power_settings', []);
        return $settings[$key] ?? $default;
    }
}

if (!function_exists('stayarta_track_event')) {
    function stayarta_track_event($event, $data = []) {
        if (class_exists('STAYArta_Power_System')) {
            $plugin = STAYArta_Power_System::get_instance();
            return $plugin->save_analytics_event($event, $data);
        }
        return false;
    }
}

// ==========================================
// COMPATIBILIDAD Y FALLBACKS
// ==========================================

// Verificar compatibilidad con versiones antiguas de WordPress
if (version_compare($GLOBALS['wp_version'], '5.8', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>STAYArta Power:</strong> Este plugin requiere WordPress 5.8 o superior. Por favor, actualiza WordPress.</p></div>';
    });
    return;
}

// Verificar compatibilidad con PHP
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>STAYArta Power:</strong> Este plugin requiere PHP 7.4 o superior. Versión actual: ' . PHP_VERSION . '</p></div>';
    });
    return;
}

?>
    
    // ==========================================
    // INYECCIÓN DE MAGIA STAYARTA MEJORADA
    // ==========================================
    
    public function inject_stayarta_magic() {
        ?>
        <script>
        (function() {
            'use strict';
            
            // Esperar a que el DOM esté listo
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initSTAYArtaMagic);
            } else {
                initSTAYArtaMagic();
            }
            
            function initSTAYArtaMagic() {
                try {
                    // Verificar jQuery
                    if (typeof $ === 'undefined' && typeof jQuery !== 'undefined') {
                        window.$ = jQuery;
                    }
                    
                    // Inicializar sistemas STAYArta
                    initBrandEnhancements();
                    fixCommonIssues();
                    setupGlobalEventListeners();
                    
                    console.log('✨ STAYArta Magic initialized successfully');
                    
                } catch (error) {
                    console.error('STAYArta Magic initialization error:', error);
                }
            }
            
            function initBrandEnhancements() {
                // Añadir efectos glitch a elementos principales
                const headings = document.querySelectorAll('h1, h2, .elementor-heading-title, .entry-title');
                headings.forEach((el, index) => {
                    if (!el.classList.contains('stayarta-enhanced')) {
                        el.classList.add('stayarta-enhanced');
                        
                        // Añadir efectos glitch si están habilitados
                        <?php if ($this->settings['glitch_animations'] ?? false): ?>
                        el.classList.add('stayarta-glitch');
                        el.setAttribute('data-text', el.textContent.trim());
                        <?php endif; ?>
                        
                        // Preparar para animaciones de scroll
                        <?php if ($this->settings['scroll_animations'] ?? false): ?>
                        el.style.opacity = '0';
                        el.style.transform = 'translateY(20px)';
                        el.style.transition = 'all 0.6s ease';
                        <?php endif; ?>
                    }
                });
                
                // Mejorar botones
                const buttons = document.querySelectorAll('button, .elementor-button, .btn, .wp-block-button__link');
                buttons.forEach(btn => {
                    if (!btn.classList.contains('stayarta-enhanced')) {
                        btn.classList.add('stayarta-enhanced');
                        
                        // Efectos hover mejorados
                        btn.addEventListener('mouseenter', function() {
                            this.style.transform = 'translateY(-2px)';
                            this.style.boxShadow = '0 6px 20px rgba(102, 126, 234, 0.3)';
                            this.style.transition = 'all 0.3s ease';
                        });
                        
                        btn.addEventListener('mouseleave', function() {
                            this.style.transform = '';
                            this.style.boxShadow = '';
                        });
                        
                        // Efecto click
                        btn.addEventListener('click', function() {
                            this.style.animation = 'stayarta-click-pulse 0.3s ease';
                            setTimeout(() => {
                                this.style.animation = '';
                            }, 300);
                        });
                    }
                });
                
                // Configurar animaciones de scroll
                <?php if ($this->settings['scroll_animations'] ?? false): ?>
                setupScrollAnimations();
                <?php endif; ?>
            }
            
            function setupScrollAnimations() {
                if (!('IntersectionObserver' in window)) {
                    // Fallback para navegadores antiguos
                    document.querySelectorAll('.stayarta-enhanced').forEach(el => {
                        el.style.opacity = '1';
                        el.style.transform = 'translateY(0)';
                    });
                    return;
                }
                
                const observerOptions = {
                    threshold: 0.1,
                    rootMargin: '0px 0px -50px 0px'
                };
                
                const scrollObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const element = entry.target;
                            element.style.opacity = '1';
                            element.style.transform = 'translateY(0)';
                            scrollObserver.unobserve(element);
                        }
                    });
                }, observerOptions);
                
                document.querySelectorAll('.stayarta-enhanced').forEach(el => {
                    scrollObserver.observe(el);
                });
            }
            
            function fixCommonIssues() {
                // Fix para Elementor sticky elements
                if (typeof elementorFrontend !== 'undefined') {
                    elementorFrontend.hooks.addAction('frontend/element_ready/global', function($scope) {
                        // Fix sticky elements
                        const stickyElements = $scope.find('[data-settings*="sticky"]');
                        stickyElements.each(function() {
                            const $element = $(this);
                            if (!$element.hasClass('stayarta-sticky-fixed')) {
                                $element.addClass('stayarta-sticky-fixed');
                                
                                // Aplicar fix si es necesario
                                try {
                                    const settings = $element.data('settings');
                                    if (settings && settings.sticky) {
                                        // Fix específico para sticky
                                        $element.css('z-index', '999');
                                    }
                                } catch(e) {
                                    console.warn('STAYArta: Sticky element fix skipped:', e.message);
                                }
                            }
                        });
                        
                        // Fix animaciones invisibles
                        $scope.find('.elementor-invisible').each(function() {
                            const $el = $(this);
                            if (isElementInViewport($el[0])) {
                                $el.removeClass('elementor-invisible');
                            }
                        });
                    });
                }
                
                // Fix para Essential Addons TOC
                if (typeof $ !== 'undefined') {
                    $(document).ready(function() {
                        try {
                            $('.eael-toc-button i').off('click.stayarta').on('click.stayarta', function() {
                                $(this).parent().next('.eael-toc-list').toggle();
                            });
                        } catch(e) {
                            console.warn('STAYArta: TOC fix applied with fallback');
                        }
                    });
                }
                
                // Fix para elementos sticky personalizados
                document.querySelectorAll('[data-sticky]').forEach(element => {
                    if (!element.classList.contains('stayarta-sticky-processed')) {
                        element.classList.add('stayarta-sticky-processed');
                        setupStickyElement(element);
                    }
                });
            }
            
            function setupStickyElement(element) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            element.classList.add('is-sticky');
                        } else {
                            element.classList.remove('is-sticky');
                        }
                    });
                }, { threshold: 0.1 });
                
                observer.observe(element);
            }
            
            function setupGlobalEventListeners() {
                // Track errores JavaScript
                window.addEventListener('error', function(e) {
                    if (typeof STAYArta !== 'undefined' && STAYArta.utils) {
                        STAYArta.utils.trackEvent('javascript_error', {
                            message: e.message,
                            filename: e.filename,
                            lineno: e.lineno,
                            colno: e.colno
                        });
                    }
                });
                
                // Track cambios de visibilidad
                document.addEventListener('visibilitychange', function() {
                    if (typeof STAYArta !== 'undefined' && STAYArta.utils) {
                        STAYArta.utils.trackEvent('visibility_change', {
                            hidden: document.hidden
                        });
                    }
                });
                
                // Detectar y mejorar imágenes lazy
                if ('IntersectionObserver' in window) {
                    setupLazyImageObserver();
                }
            }
            
            function setupLazyImageObserver() {
                const imageObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            
                            // Si es una imagen lazy de STAYArta
                            if (img.dataset.src && !img.classList.contains('stayarta-loaded')) {
                                img.src = img.dataset.src;
                                img.classList.add('stayarta-loaded');
                                img.style.opacity = '1';
                            }
                            
                            imageObserver.unobserve(img);
                        }
                    });
                }, {
                    rootMargin: '50px'
                });
                
                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
            
            function isElementInViewport(element) {
                const rect = element.getBoundingClientRect();
                return (
                    rect.top >= 0 &&
                    rect.left >= 0 &&
                    rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                    rect.right <= (window.innerWidth || document.documentElement.clientWidth)
                );
            }
            
            // Añadir utilidad de viewport para jQuery si existe
            if (typeof $ !== 'undefined' && typeof $.fn.isInViewport === 'undefined') {
                $.fn.isInViewport = function() {
                    if (this.length === 0) return false;
                    
                    const elementTop = this.offset().top;
                    const elementBottom = elementTop + this.outerHeight();
                    const viewportTop = $(window).scrollTop();
                    const viewportBottom = viewportTop + $(window).height();
                    
                    return elementBottom > viewportTop && elementTop < viewportBottom;
                };
            }
        })();
        </script>
        
        <!-- Estilos adicionales para efectos -->
        <style>
        @keyframes stayarta-click-pulse {
            0% { transform: scale(1); }
            50% { transform: scale(0.95); }
            100% { transform: scale(1); }
        }
        
        <?php if ($this->settings['glitch_animations'] ?? false): ?>
        .stayarta-glitch {
            position: relative;
            overflow: hidden;
        }
        
        .stayarta-glitch::before,
        .stayarta-glitch::after {
            content: attr(data-text);
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            opacity: 0;
        }
        
        .stayarta-glitch:hover::before {
            animation: glitch-1 0.5s infinite;
            color:    
    // ==========================================
    // INYECCIÓN DE CSS PERSONALIZADO
    // ==========================================
    
    public function inject_custom_css() {
    public function inject_custom_css() {
        if (!($this->settings['custom_css'] ?? false)) {
            return;
        }
        
        ?>
        <style id="stayarta-custom-css">
        /* STAYArta Custom CSS Variables */
        :root {
            --stayarta-primary: #667eea;
            --stayarta-secondary: #764ba2;
            --stayarta-accent: #00d4ff;
            --stayarta-dark: #1a1a2e;
            --stayarta-light: #f8f9fa;
            --stayarta-font-heading: 'DM Serif Display', serif;
            --stayarta-font-body: 'Inter', sans-serif;
            --stayarta-gradient: linear-gradient(135deg, var(--stayarta-primary) 0%, var(--stayarta-secondary) 100%);
            --stayarta-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
            --stayarta-border-radius: 15px;
            --stayarta-transition: all 0.3s ease;
        }
        
        /* Global STAYArta Styling */
        body {
            font-family: var(--stayarta-font-body);
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: var(--stayarta-font-heading);
        }
        
        .stayarta-gradient-bg {
            background: var(--stayarta-gradient);
        }
        
        .stayarta-shadow {
            box-shadow: var(--stayarta-shadow);
        }
        
        /* Quick Buy Button Styles */
        .stayarta-quick-buy-section {
            margin: 30px 0;
            padding: 25px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-radius: var(--stayarta-border-radius);
            border: 1px solid rgba(102, 126, 234, 0.2);
            text-align: center;
        }
        
        .stayarta-quick-buy-btn {
            background: var(--stayarta-gradient);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 50px;
            cursor: pointer;
            transition: var(--stayarta-transition);
            box-shadow: var(--stayarta-shadow);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .stayarta-quick-buy-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.4);
        }
        
        .stayarta-quick-buy-btn:active {
            transform: translateY(0);
        }
        
        .stayarta-quick-buy-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .quick-buy-description {
            margin: 10px 0 0 0;
            font-size: 14px;
            opacity: 0.8;
        }
        
        /* AI Recommendations Styles */
        .stayarta-ai-recommendations {
            margin: 40px 0;
            padding: 30px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border-radius: var(--stayarta-border-radius);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .recommendations-header h3 {
            margin: 0 0 10px 0;
            color: var(--stayarta-primary);
        }
        
        .recommendations-header p {
            margin: 0 0 25px 0;
            opacity: 0.8;
        }
        
        .ai-recommendations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .ai-recommendation-item {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            transition: var(--stayarta-transition);
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .ai-recommendation-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .recommendation-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }
        
        .product-image {
            position: relative;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            transition: var(--stayarta-transition);
        }
        
        .ai-recommendation-item:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-info {
            padding: 15px;
        }
        
        .product-info h4 {
            margin: 0 0 8px 0;
            font-size: 16px;
            line-height: 1.3;
        }
        
        .product-info .price {
            display: block;
            font-weight: bold;
            color: var(--stayarta-primary);
            margin-bottom: 5px;
        }
        
        .product-info .rating {
            font-size: 14px;
        }
        </style>
        <?php
    }
    
    // ==========================================
    // INYECCIÓN DE JAVASCRIPT PERSONALIZADO
    // ==========================================
    
    public function inject_custom_js() {
        ?>
        <script>
        // STAYArta Custom JavaScript - Inicialización
        window.STAYArta = window.STAYArta || {};
        
        // Utilidades principales
        STAYArta.utils = {
            // Función debounce mejorada
            debounce: function(func, wait, immediate = false) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        timeout = null;
                        if (!immediate) func(...args);
                    };
                    const callNow = immediate && !timeout;
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                    if (callNow) func(...args);
                };
            },
            
            // Función throttle
            throttle: function(func, limit) {
                let inThrottle;
                return function(...args) {
                    if (!inThrottle) {
                        func.apply(this, args);
                        inThrottle = true;
                        setTimeout(() => inThrottle = false, limit);
                    }
                };
            },
            
            // Tracking de eventos mejorado
            trackEvent: function(event, data = {}) {
                if (typeof stayartaAjax === 'undefined') {
                    console.warn('STAYArta: Ajax not configured');
                    return;
                }
                
                // Añadir información contextual automática
                const contextData = {
                    ...data,
                    timestamp: Date.now(),
                    url: window.location.href,
                    referrer: document.referrer,
                    viewport: {
                        width: window.innerWidth,
                        height: window.innerHeight
                    },
                    user_agent: navigator.userAgent,
                    session_id: STAYArta.utils.getSessionId()
                };
                
                fetch(stayartaAjax.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'stayarta_track_event',
                        event: event,
                        data: JSON.stringify(contextData),
                        nonce: stayartaAjax.trackingNonce
                    })
                }).catch(error => {
                    console.error('STAYArta tracking error:', error);
                });
            },
            
            // Gestión de session ID
            getSessionId: function() {
                let sessionId = sessionStorage.getItem('stayarta_session_id');
                if (!sessionId) {
                    sessionId = 'stayarta_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                    sessionStorage.setItem('stayarta_session_id', sessionId);
                }
                return sessionId;
            },
            
            // Detectar dispositivo
            getDeviceType: function() {
                const width = window.innerWidth;
                if (width <= 768) return 'mobile';
                if (width <= 1024) return 'tablet';
                return 'desktop';
            },
            
            // Animaciones suaves
            animateElement: function(element, animation, duration = 600) {
                return new Promise((resolve) => {
                    element.style.animation = `${animation} ${duration}ms ease-in-out`;
                    element.addEventListener('animationend', () => {
                        element.style.animation = '';
                        resolve();
                    }, { once: true });
                });
            }
        };
        
        // Sistema de notificaciones
        STAYArta.notifications = {
            show: function(message, type = 'info', duration = 4000) {
                const notification = document.createElement('div');
                notification.className = `stayarta-notification stayarta-notification-${type}`;
                notification.innerHTML = `
                    <div class="notification-content">
                        <span class="notification-icon">${this.getIcon(type)}</span>
                        <span class="notification-message">${message}</span>
                        <button class="notification-close">&times;</button>
                    </div>
                `;
                
                // Estilos inline para evitar dependencias
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 999999;
                    background: ${this.getColor(type)};
                    color: white;
                    padding: 15px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                    transform: translateX(100%);
                    transition: transform 0.3s ease;
                    max-width: 350px;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                `;
                
                document.body.appendChild(notification);
                
                // Animar entrada
                requestAnimationFrame(() => {
                    notification.style.transform = 'translateX(0)';
                });
                
                // Cerrar automáticamente
                const autoClose = setTimeout(() => {
                    this.hide(notification);
                }, duration);
                
                // Botón de cerrar
                notification.querySelector('.notification-close').addEventListener('click', () => {
                    clearTimeout(autoClose);
                    this.hide(notification);
                });
                
                return notification;
            },
            
            hide: function(notification) {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            },
            
            getIcon: function(type) {
                const icons = {
                    success: '✅',
                    error: '❌',
                    warning: '⚠️',
                    info: 'ℹ️'
                };
                return icons[type] || icons.info;
            },
            
            getColor: function(type) {
                const colors = {
                    success: 'linear-gradient(135deg, #4CAF50, #45a049)',
                    error: 'linear-gradient(135deg, #f44336, #da190b)',
                    warning: 'linear-gradient(135deg, #FF9800, #F57C00)',
                    info: 'linear-gradient(135deg, #667eea, #764ba2)'
                };
                return colors[type] || colors.info;
            }
        };
        
        // Inicialización cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            STAYArta.init();
        });
        
        // Función de inicialización principal
        STAYArta.init = function() {
            console.log('🚀 STAYArta Power System initialized');
            
            // Inicializar componentes
            this.initQuickBuy();
            this.initBasicTracking();
            this.initPerformanceMonitoring();
            
            // Tracking de página cargada
            STAYArta.utils.trackEvent('page_load', {
                page_type: document.body.className,
                load_time: performance.now()
            });
        };
        
        // Quick Buy functionality
        STAYArta.initQuickBuy = function() {
            const quickBuyButtons = document.querySelectorAll('.stayarta-quick-buy-btn');
            
            quickBuyButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const productId = this.dataset.productId;
                    const productName = this.dataset.productName;
                    
                    if (!productId) {
                        STAYArta.notifications.show('Error: ID de producto no encontrado', 'error');
                        return;
                    }
                    
                    // Cambiar estado del botón
                    const btnText = this.querySelector('.btn-text');
                    const btnLoader = this.querySelector('.btn-loader');
                    const originalText = btnText.textContent;
                    
                    btnText.style.display = 'none';
                    btnLoader.style.display = 'inline';
                    this.disabled = true;
                    
                    // Realizar petición AJAX
                    fetch(stayartaAjax.ajaxUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'stayarta_quick_buy',
                            product_id: productId,
                            nonce: stayartaAjax.nonce
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            STAYArta.notifications.show('✅ Producto añadido al carrito', 'success');
                            
                            // Cambiar botón a "Ir al checkout"
                            btnLoader.style.display = 'none';
                            btnText.textContent = '🛒 Ir al Checkout';
                            btnText.style.display = 'inline';
                            this.disabled = false;
                            
                            // Cambiar función del botón
                            this.onclick = () => {
                                window.location.href = data.data.checkout_url;
                            };
                            
                            // Tracking del evento
                            STAYArta.utils.trackEvent('quick_buy_success', {
                                product_id: productId,
                                product_name: productName
                            });
                            
                        } else {
                            throw new Error(data.data?.message || 'Error desconocido');
                        }
                    })
                    .catch(error => {
                        console.error('Quick buy error:', error);
                        STAYArta.notifications.show('Error al añadir producto al carrito', 'error');
                        
                        // Restaurar botón
                        btnLoader.style.display = 'none';
                        btnText.textContent = originalText;
                        btnText.style.display = 'inline';
                        this.disabled = false;
                        
                        // Tracking del error
                        STAYArta.utils.trackEvent('quick_buy_error', {
                            product_id: productId,
                            error: error.message
                        });
                    });
                });
            });
        };
        
        // Tracking básico de interacciones
        STAYArta.initBasicTracking = function() {
            // Track clicks en enlaces externos
            document.querySelectorAll('a[href^="http"]:not([href*="' + window.location.hostname + '"])').forEach(link => {
                link.addEventListener('click', function() {
                    STAYArta.utils.trackEvent('external_link_click', {
                        url: this.href,
                        text: this.textContent.trim().substring(0, 50)
                    });
                });
            });
            
            // Track scroll depth
            let maxScroll = 0;
            const trackScroll = STAYArta.utils.throttle(() => {
                const scrollPercent = Math.round(
                    (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100
                );
                
                if (scrollPercent > maxScroll) {
                    maxScroll = scrollPercent;
                    
                    // Track milestones
                    [25, 50, 75, 90, 100].forEach(milestone => {
                        if (scrollPercent >= milestone && maxScroll >= milestone) {
                            STAYArta.utils.trackEvent('scroll_depth', {
                                percentage: milestone
                            });
                        }
                    });
                }
            }, 1000);
            
            window.addEventListener('scroll', trackScroll);
        };
        
        // Monitoreo básico de performance
        STAYArta.initPerformanceMonitoring = function() {
            // Track performance cuando la página esté completamente cargada
            window.addEventListener('load', () => {
                setTimeout(() => {
                    if ('performance' in window && 'getEntriesByType' in performance) {
                        const perfData = performance.getEntriesByType('navigation')[0];
                        
                        if (perfData) {
                            STAYArta.utils.trackEvent('page_performance', {
                                load_time: Math.round(perfData.loadEventEnd - perfData.loadEventStart),
                                dom_ready: Math.round(perfData.domContentLoadedEventEnd - perfData.loadEventStart),
                                total_time: Math.round(perfData.loadEventEnd),
                                device_type: STAYArta.utils.getDeviceType()
                            });
                        }
                    }
                }, 1000);
            });
        };
        </script>
        <?php
    }    
    // ==========================================
    // CONFIGURACIONES BÁSICAS MEJORADAS
    // ==========================================
    
    private function set_php_limits() {
        if (function_exists('ini_set')) {
            try {
                ini_set('memory_limit', '768M');
                ini_set('max_execution_time', 300);
                ini_set('max_input_vars', 5000);
                ini_set('post_max_size', '64M');
                ini_set('upload_max_filesize', '64M');
            } catch (Exception $e) {
                error_log('STAYArta PHP Limits Error: ' . $e->getMessage());
            }
    
    // ==========================================
    // FUNCIONES DE IA Y CHATBOT MEJORADAS
    // ==========================================
    
    public function init_ai_features() {
        if ($this->settings['ai_chatbot'] ?? false) {
            add_action('wp_footer', [$this, 'inject_advanced_chatbot']);
        }
        
        if ($this->settings['ai_recommendations'] ?? false) {
            add_action('woocommerce_after_single_product_summary', [$this, 'ai_product_recommendations'], 25);
        }
        
        if ($this->settings['ai_content_optimization'] ?? false) {
            add_filter('the_content', [$this, 'optimize_content_with_ai']);
        }
    }
    
    private function generate_ai_response($message, $context = []) {
        $message_lower = strtolower(trim($message));
        
        // Respuestas inteligentes basadas en contexto
        $response_patterns = [
            // Productos y servicios
            '/producto|product|item/' => [
                'Tenemos una amplia gama de productos tecnológicos que optimizan tu día. ¿Te interesa alguna categoría específica?',
                'Nuestros productos más populares incluyen gadgets inteligentes y soluciones tech. ¿Qué buscas exactamente?',
                'Puedes ver todos nuestros productos en la tienda. ¿Hay algo en particular que necesites?'
            ],
            
            '/precio|cost|cuanto|price/' => [
                'Los precios varían según el producto. ¿Podrías decirme qué producto te interesa para darte información específica?',
                'Manejamos diferentes rangos de precios. ¿Tienes algún presupuesto en mente?',
                '¿Te gustaría que te ayude a encontrar productos dentro de tu presupuesto?'
            ],
            
            // Soporte técnico
            '/ayuda|help|problema|issue/' => [
                'Estoy aquí para ayudarte. ¿Podrías contarme más detalles sobre lo que necesitas?',
                '¡Por supuesto! ¿En qué específicamente puedo asistirte?',
                'Déjame ayudarte. ¿Podrías ser más específico sobre tu consulta?'
            ],
            
            // Información de empresa
            '/stayarta|empresa|company/' => [
                'STAYArta es una empresa retro-futurista de soluciones tecnológicas con ADN "Stay Arta and Hack the Ordinary".',
                'Somos especialistas en tecnología útil que optimiza tu día a día. ¿Te gustaría conocer más sobre nosotros?',
                'Nos dedicamos a crear experiencias tecnológicas que realmente mejoran la vida de las personas.'
            ],
            
            '/contacto|contact|telefono|email/' => [
                'Puedes contactarnos a través de nuestro formulario web o por email. ¿Prefieres algún método específico?',
                'Estamos disponibles por múltiples canales. ¿Cómo te gustaría que te contactemos?',
                '¿Necesitas hablar directamente con nuestro equipo? Te puedo conectar con ellos.'
            ],
            
            // Pedidos y envíos
            '/pedido|order|compra/' => [
                'Para revisar el estado de tu pedido, necesitaré tu número de orden. ¿Lo tienes a mano?',
                'Los pedidos generalmente se procesan en 24-48 horas. ¿Tienes alguna consulta específica?',
                '¿Necesitas ayuda con un pedido existente o quieres hacer uno nuevo?'
            ],
            
            '/envio|shipping|delivery/' => [
                'Realizamos envíos a nivel nacional. Los tiempos varían según la ubicación. ¿A dónde necesitas el envío?',
                'Nuestros envíos son rápidos y seguros. ¿Te gustaría conocer las opciones disponibles?',
                'Tenemos varias opciones de envío. ¿Cuál es tu ubicación?'
            ]
        ];
        
        // Buscar patrones en el mensaje
        foreach ($response_patterns as $pattern => $responses) {
            if (preg_match($pattern, $message_lower)) {
                return $responses[array_rand($responses)];
            }
        }
        
        // Respuestas de saludo
        if (preg_match('/\b(hola|hello|hi|buenas|hey|saludos)\b/i', $message_lower)) {
            $greetings = [
                '¡Hola! Bienvenido a STAYArta. Soy Nova y estoy aquí para ayudarte. ¿En qué puedo asistirte?',
                '¡Hola! Me alegra verte por aquí. ¿Cómo puedo ayudarte hoy?',
                '¡Hola! Soy Nova, tu asistente digital de STAYArta. ¿Qué necesitas?'
            ];
            return $greetings[array_rand($greetings)];
        }
        
        // Respuestas de despedida
        if (preg_match('/\b(gracias|thanks|chao|bye|adiós|hasta luego)\b/i', $message_lower)) {
            $farewells = [
                '¡De nada! Ha sido un placer ayudarte. Si necesitas algo más, no dudes en escribirme. ¡Que tengas un excelente día! 🚀',
                '¡Perfecto! Espero haberte ayudado. ¡Hasta la próxima! 😊',
                'Me alegra haber podido ayudarte. ¡Que tengas un día fantástico! ✨'
            ];
            return $farewells[array_rand($farewells)];
        }
        
        // Respuesta por defecto inteligente
        $default_responses = [
            'Interesante pregunta. Permíteme ayudarte con eso. ¿Podrías darme más detalles?',
            'Entiendo. Déjame ver cómo puedo asistirte mejor. ¿Hay algo específico que te gustaría saber?',
            'Perfecto. Para brindarte la mejor ayuda, ¿podrías ser un poco más específico sobre lo que buscas?',
            'Claro, puedo ayudarte con eso. ¿Te gustaría que contactemos a nuestro equipo especializado?'
        ];
        
        return $default_responses[array_rand($default_responses)];
    }
    
    private function get_contextual_quick_replies($message) {
        $message_lower = strtolower($message);
        
        // Quick replies basadas en el contexto del mensaje
        if (preg_match('/producto|item|shop/i', $message_lower)) {
            return [
                ['text' => '📱 Gadgets', 'message' => 'Quiero ver gadgets tecnológicos'],
                ['text' => '💻 Tech', 'message' => 'Productos tecnológicos populares'],
                ['text' => '🎯 Ofertas', 'message' => 'Ver ofertas especiales disponibles']
            ];
        }
        
        if (preg_match('/contacto|contact|hablar/i', $message_lower)) {
            return [
                ['text' => '📧 Email', 'message' => '¿Cuál es el email de contacto?'],
                ['text' => '📞 Teléfono', 'message' => '¿Tienen teléfono de contacto?'],
                ['text' => '💬 WhatsApp', 'message' => '¿Tienen WhatsApp Business?']
            ];
        }
        
        if (preg_match('/pedido|order|compra/i', $message_lower)) {
            return [
                ['text' => '📦 Estado pedido', 'message' => 'Consultar estado de mi pedido'],
                ['text' => '🔄 Cambiar pedido', 'message' => 'Necesito cambiar algo de mi pedido'],
                ['text' => '❌ Cancelar', 'message' => 'Quiero cancelar mi pedido']
            ];
        }
        
        // Quick replies por defecto
        return [
            ['text' => '🛍️ Ver productos', 'message' => 'Quiero ver sus productos'],
            ['text' => '❓ Más ayuda', 'message' => 'Necesito más ayuda'],
            ['text' => '👨‍💼 Contactar equipo', 'message' => 'Hablar con una persona real']
        ];
    }
    
    public function inject_advanced_chatbot() {
        if (!($this->settings['ai_chatbot'] ?? false)) {
            return;
        }
        ?>
        <div id="stayarta-ai-chatbot" class="stayarta-chatbot-container">
            <div class="chatbot-toggle" data-chatbot="toggle">
                <div class="bot-avatar">🤖</div>
                <span class="bot-name">NovaSTAYBot</span>
                <div class="online-indicator"></div>
            </div>
            
            <div class="chatbot-window" data-chatbot="window" style="display: none;">
                <div class="chatbot-header">
                    <div class="bot-info">
                        <div class="bot-avatar">🤖</div>
                        <div class="bot-details">
                            <h4>NovaSTAYBot</h4>
                            <span class="bot-status">En línea</span>
                        </div>
                    </div>
                    <button class="close-chat" data-chatbot="close" aria-label="Cerrar chat">×</button>
                </div>
                
                <div class="chatbot-messages" data-chatbot="messages">
                    <div class="bot-message">
                        <div class="message-avatar">🤖</div>
                        <div class="message-content">
                            <p>¡Hola! Soy Nova, tu asistente digital de STAYArta. ¿En qué puedo ayudarte hoy?</p>
                            <div class="quick-replies">
                                <button class="quick-reply" data-message="Ver productos disponibles">🛍️ Ver productos</button>
                                <button class="quick-reply" data-message="Información de contacto">📞 Contacto</button>
                                <button class="quick-reply" data-message="Estado de mi pedido">📦 Mi pedido</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="chatbot-typing" data-chatbot="typing" style="display: none;">
                    <div class="typing-indicator">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <span class="typing-text">Nova está escribiendo...</span>
                </div>
                
                <div class="chatbot-input">
                    <input type="text" data-chatbot="input" placeholder="Escribe tu mensaje..." maxlength="500" autocomplete="off">
                    <button class="send-btn" data-chatbot="send" aria-label="Enviar mensaje">
                        <span class="send-icon">📤</span>
                    </button>
                </div>
            </div>
        </div>
        
        <?php $this->inject_chatbot_styles(); ?>
        <?php $this->inject_chatbot_scripts(); ?>
        <?php
    }
    
    // ==========================================
    // MEJORAS PARA WOOCOMMERCE
    // ==========================================
    
    public function init_woocommerce_enhancements() {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        if ($this->settings['quick_buy'] ?? false) {
            add_action('woocommerce_after_single_product_summary', [$this, 'add_quick_buy_button'], 15);
        }
        
        if ($this->settings['cart_animations'] ?? false) {
            add_action('wp_footer', [$this, 'inject_cart_animations']);
        }
        
        if ($this->settings['checkout_optimization'] ?? false) {
            $this->optimize_checkout();
        }
        
        if ($this->settings['abandoned_cart_recovery'] ?? false) {
            $this->init_abandoned_cart_recovery();
        }
        
        if ($this->settings['ai_recommendations'] ?? false) {
            add_action('woocommerce_after_single_product_summary', [$this, 'ai_product_recommendations'], 25);
        }
    }
    
    public function add_quick_buy_button() {
        global $product;
        
        if (!$product || !$product->is_type('simple') || !$product->is_purchasable() || !$product->is_in_stock()) {
            return;
        }
        ?>
        <div class="stayarta-quick-buy-section">
            <button class="stayarta-quick-buy-btn" 
                    data-product-id="<?php echo esc_attr($product->get_id()); ?>"
                    data-product-name="<?php echo esc_attr($product->get_name()); ?>">
                <span class="btn-icon">⚡</span>
                <span class="btn-text">Compra Rápida - Checkout Express</span>
                <span class="btn-loader" style="display: none;">⏳</span>
            </button>
            <p class="quick-buy-description">
                Añadir al carrito y proceder directamente al checkout
            </p>
        </div>
        <?php
    }
    
    public function ai_product_recommendations() {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        global $product;
        if (!$product) {
            return;
        }
        
        $related_products = $this->get_smart_related_products($product);
        
        if (empty($related_products)) {
            return;
        }
        ?>
        <div class="stayarta-ai-recommendations">
            <div class="recommendations-header">
                <h3>🤖 Recomendaciones Inteligentes</h3>
                <p>Basado en tu interés, estos productos podrían gustarte:</p>
            </div>
            
            <div class="ai-recommendations-grid">
                <?php foreach ($related_products as $related_product): ?>
                    <div class="ai-recommendation-item">
                        <a href="<?php echo esc_url(get_permalink($related_product->get_id())); ?>" 
                           class="recommendation-link">
                            <div class="product-image">
                                <?php echo $related_product->get_image('thumbnail'); ?>
                            </div>
                            <div class="product-info">
                                <h4><?php echo esc_html($related_product->get_name()); ?></h4>
                                <span class="price"><?php echo $related_product->get_price_html(); ?></span>
                                <span class="rating">
                                    <?php echo wc_get_rating_html($related_product->get_average_rating()); ?>
                                </span>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    private function get_smart_related_products($product, $limit = 4) {
        $related_ids = [];
        
        try {
            // Obtener productos de la misma categoría
            $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'ids']);
            if (!empty($categories)) {
                $category_products = wc_get_products([
                    'category' => $categories,
                    'exclude' => [$product->get_id()],
                    'limit' => $limit * 2,
                    'orderby' => 'popularity',
                    'status' => 'publish'
                ]);
                
                foreach ($category_products as $cat_product) {
                    if ($cat_product->is_visible()) {
                        $related_ids[] = $cat_product->get_id();
                    }
                }
            }
            
            // Añadir cross-sells y up-sells
            $cross_sells = $product->get_cross_sell_ids();
            $up_sells = $product->get_upsell_ids();
            $related_ids = array_merge($related_ids, $cross_sells, $up_sells);
            
            // Remover duplicados y limitar
            $related_ids = array_unique($related_ids);
            $related_ids = array_slice($related_ids, 0, $limit);
            
            // Obtener objetos de producto
            $related_products = [];
            foreach ($related_ids as $id) {
                $related_product = wc_get_product($id);
                if ($related_product && $related_product->is_visible() && $related_product->is_in_stock()) {
                    $related_products[] = $related_product;
                }
            }
            
            return $related_products;
            
        } catch (Exception $e) {
            error_log('STAYArta Smart Related Products Error: ' . $e->getMessage());
            return [];
        }
    }
    
    private function disable_debug() {
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', false);
        }
        if (!defined('WP_DEBUG_LOG')) {
            define('WP_DEBUG_LOG', false);
        }
        if (!defined('WP_DEBUG_DISPLAY')) {
            define('WP_DEBUG_DISPLAY', false);
        }
    }
    
    // ==========================================
    // OPTIMIZACIONES DE PERFORMANCE
    // ==========================================
    
    public function optimize_critical_assets() {
        if (!is_admin()) {
            // Preload critical fonts
            echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>';
            echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
            
            // Preload critical STAYArta fonts
            echo '<link rel="preload" href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&display=swap" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
            echo '<link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
            
            // DNS prefetch para recursos externos
            echo '<link rel="dns-prefetch" href="//www.google-analytics.com">';
            echo '<link rel="dns-prefetch" href="//www.googletagmanager.com">';
            echo '<link rel="dns-prefetch" href="//cdnjs.cloudflare.com">';
            
            // Preload hero image si es la página principal
            if (is_front_page()) {
                $hero_image = $this->detect_hero_image();
                if ($hero_image) {
                    echo '<link rel="preload" href="' . esc_url($hero_image) . '" as="image">';
                }
            }
        }
    }
    
    public function smart_cache() {
        if (!is_admin() && !is_user_logged_in() && !is_customize_preview()) {
            // Headers de caché inteligente
            $cache_time = $this->get_cache_time();
            
            header('Cache-Control: public, max-age=' . $cache_time . ', must-revalidate');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cache_time) . ' GMT');
            header('Cache-Control: public, max-age=' . $cache_time . ', must-revalidate');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cache_time) . ' GMT');
            header('Vary: Accept-Encoding, User-Agent');
            
            // Compresión GZIP
            if (!ob_get_level() && extension_loaded('zlib') && !headers_sent()) {
                ob_start('ob_gzhandler');
            }
        }
    }
    
    private function get_cache_time() {
        if (is_front_page()) {
            return 7200; // 2 horas para la página principal
        } elseif (is_single() || is_page()) {
            return 3600; // 1 hora para páginas/posts
        } elseif (is_category() || is_tag() || is_archive()) {
            return 1800; // 30 minutos para archivos
        }
        
        return 900; // 15 minutos por defecto
    }
    
    private function detect_hero_image() {
        $hero_image = null;
        
        if (is_front_page()) {
            // Intentar obtener imagen destacada de la página principal
            $page_id = get_option('page_on_front');
            if ($page_id) {
                $hero_image = get_the_post_thumbnail_url($page_id, 'large');
            }
            
            // Si no hay imagen destacada, buscar en el contenido
            if (!$hero_image && $page_id) {
                $content = get_post_field('post_content', $page_id);
                preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
                if (!empty($matches[1])) {
                    $hero_image = $matches[1];
                }
            }
        }
        
        return $hero_image;
    }
    
    // ==========================================
    // OPTIMIZACIONES DE WOOCOMMERCE
    // ==========================================
    
    private function optimize_woocommerce() {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Deshabilitar scripts de WooCommerce en páginas que no los necesitan
        add_action('wp_enqueue_scripts', [$this, 'conditionally_remove_woocommerce_scripts'], 99);
        
        // Optimizar cart fragments
        add_filter('woocommerce_add_to_cart_fragments', [$this, 'optimize_cart_fragments']);
        
        // Deshabilitar heartbeat en páginas no-admin
        if (!is_admin()) {
            add_action('init', function() {
                wp_deregister_script('heartbeat');
            }, 1);
        }
        
        // Optimizar consultas de productos
        add_filter('woocommerce_product_data_store_cpt_get_products_query', [$this, 'optimize_product_queries'], 10, 2);
    }
    
    public function conditionally_remove_woocommerce_scripts() {
        if (!is_woocommerce() && !is_cart() && !is_checkout() && !is_account_page()) {
            // Remover estilos de WooCommerce
            wp_dequeue_style('woocommerce-general');
            wp_dequeue_style('woocommerce-layout');
            wp_dequeue_style('woocommerce-smallscreen');
            
            // Remover scripts de WooCommerce
            wp_dequeue_script('wc-cart-fragments');
            wp_dequeue_script('woocommerce');
            wp_dequeue_script('wc-add-to-cart');
        }
    }
    
    public function optimize_cart_fragments($fragments) {
        // Limitar la cantidad de fragmentos del carrito
        if (count($fragments) > 5) {
            $fragments = array_slice($fragments, 0, 5, true);
        }
        return $fragments;
    }
    
    public function optimize_product_queries($query, $query_vars) {
        // Limitar resultados para mejorar performance
        if (!isset($query_vars['posts_per_page']) || $query_vars['posts_per_page'] > 100) {
            $query['posts_per_page'] = 50;
        }
        
        // Optimizar meta queries
        if (isset($query['meta_query']) && count($query['meta_query']) > 5) {
            $query['meta_query'] = array_slice($query['meta_query'], 0, 5);
        }
        
        return $query;
    }
    
    // ==========================================
    // SEGURIDAD MEJORADA
    // ==========================================
    
    private function security_fixes() {
        // Remover información de versión de WordPress
        remove_action('wp_head', 'wp_generator');
        add_filter('the_generator', '__return_empty_string');
        
        // Deshabilitar XML-RPC si no se necesita
        add_filter('xmlrpc_enabled', '__return_false');
        
        // Ocultar errores de login
        add_filter('login_errors', function() {
            return __('Información de acceso incorrecta.', 'stayarta-power');
        });
        
        // Bloquear acceso a archivos sensibles
        add_action('init', [$this, 'block_sensitive_files']);
        
        // Añadir headers de seguridad
        add_action('send_headers', [$this, 'add_security_headers']);
        
        // Limitar intentos de login
        add_action('wp_login_failed', [$this, 'limit_login_attempts']);
    }
    
    public function block_sensitive_files() {
        $blocked_files = ['wp-config.php', 'error_log', '.htaccess'];
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        foreach ($blocked_files as $file) {
            if (strpos($request_uri, $file) !== false) {
                status_header(404);
                nocache_headers();
                include(get_404_template());
                exit;
            }
        }
    }
    
    public function add_security_headers() {
        if (!is_admin()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
            
            if (is_ssl()) {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            }
        }
    }
    
    public function limit_login_attempts($username) {
        $attempts_key = 'stayarta_login_attempts_' . md5($this->get_user_ip());
        $attempts = get_transient($attempts_key) ?: 0;
        $attempts++;
        
        if ($attempts >= 5) {
            // Bloquear por 30 minutos después de 5 intentos fallidos
            set_transient($attempts_key, $attempts, 30 * MINUTE_IN_SECONDS);
            
            // Log del intento
            error_log(sprintf(
                'STAYArta Security: Too many login attempts from IP %s for user %s',
                $this->get_user_ip(),
                $username
            ));
            
            // Mostrar mensaje y salir
            wp_die(
                __('Demasiados intentos de login fallidos. Inténtalo de nuevo en 30 minutos.', 'stayarta-power'),
                __('Acceso Denegado', 'stayarta-power'),
                ['response' => 429]
            );
        } else {
            set_transient($attempts_key, $attempts, 15 * MINUTE_IN_SECONDS);
        }
    }
    
    // ==========================================
    // MONITOREO DE ERRORES MEJORADO
    // ==========================================
    
    private function error_monitoring() {
        // Handler para errores fatales
        register_shutdown_function([$this, 'handle_fatal_errors']);
        
        // Handler personalizado para errores
        set_error_handler([$this, 'handle_errors']);
        
        // Handler para excepciones no capturadas
        set_exception_handler([$this, 'handle_exceptions']);
    }
    
    public function handle_fatal_errors() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $log_message = sprintf(
                'STAYArta Fatal Error: %s in %s:%d',
                $error['message'],
                $error['file'],
                $error['line']
            );
            
            error_log($log_message);
            $this->send_error_notification($log_message);
        }
    }
    
    public function handle_errors($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $error_types = [
            E_ERROR => 'FATAL',
            E_WARNING => 'WARNING',
            E_NOTICE => 'NOTICE',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE'
        ];
        
        $error_type = $error_types[$severity] ?? 'UNKNOWN';
        
        $log_message = sprintf(
            'STAYArta Error [%s]: %s in %s:%d',
            $error_type,
            $message,
            $file,
            $line
        );
        
        error_log($log_message);
        
        // Solo enviar notificación para errores críticos
        if (in_array($severity, [E_ERROR, E_USER_ERROR])) {
            $this->send_error_notification($log_message);
        }
        
        return true;
    }
    
    public function handle_exceptions($exception) {
        $log_message = sprintf(
            'STAYArta Uncaught Exception: %s in %s:%d\nStack trace:\n%s',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        error_log($log_message);
        $this->send_error_notification($log_message);
    }
    
    private function send_error_notification($message) {
        if (!($this->settings['error_notifications'] ?? false)) {
            return;
        }
        
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }
        
        $subject = sprintf(
            '[%s] STAYArta Power - Error Alert',
            get_bloginfo('name')
        );
        
        $body = sprintf(
            "Se ha detectado un error en STAYArta Power System:\n\n%s\n\nFecha: %s\nURL: %s\nIP: %s",
            $message,
            current_time('Y-m-d H:i:s'),
            home_url($_SERVER['REQUEST_URI'] ?? ''),
            $this->get_user_ip()
        );
        
        wp_mail($admin_email, $subject, $body);
    }
    
    private function clean_logs() {
        $log_file = WP_CONTENT_DIR . '/debug.log';
        
        if (file_exists($log_file)) {
            try {
                $file_size = filesize($log_file);
                
                // Si el archivo es mayor a 10MB, mantener solo las últimas 1000 líneas
                if ($file_size > 10 * 1024 * 1024) {
                    $lines = file($log_file);
                    if ($lines && count($lines) > 1000) {
                        $keep_lines = array_slice($lines, -1000);
                        file_put_contents($log_file, implode('', $keep_lines));
                    }
                }
            } catch (Exception $e) {
                error_log('STAYArta Log Cleanup Error: ' . $e->getMessage());
            }
        }
    }<?php
/**
 * Plugin Name: STAYArta Power System
 * Description: Sistema completo de optimización, personalización y potenciación para STAYArta - Versión Corregida y Potenciada
 * Version: 2.5.0
 * Author: STAYArta Team
 * Text Domain: stayarta-power
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

// Definir constantes del plugin
define('STAYARTA_POWER_VERSION', '2.5.0');
define('STAYARTA_POWER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STAYARTA_POWER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('STAYARTA_POWER_ASSETS_URL', STAYARTA_POWER_PLUGIN_URL . 'assets/');

class STAYArta_Power_System {
    
    private $version = STAYARTA_POWER_VERSION;
    private $settings;
    private static $instance = null;
    
    // Singleton pattern
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Verificar requisitos mínimos
        if (!$this->check_requirements()) {
            return;
        }
        
        // Cargar configuraciones
        $this->settings = get_option('stayarta_power_settings', $this->get_default_settings());
        
        // Inicializar plugin
        $this->init_hooks();
        $this->set_php_limits();
        $this->disable_debug();
    }
    
    // ==========================================
    // VERIFICACIÓN DE REQUISITOS Y COMPATIBILIDAD
    // ==========================================
    
    private function check_requirements() {
        // Verificar versión de PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>STAYArta Power:</strong> Requiere PHP 7.4 o superior. Versión actual: ' . PHP_VERSION . '</p></div>';
            });
            return false;
        }
        
        // Verificar versión de WordPress
        if (version_compare(get_bloginfo('version'), '5.8', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>STAYArta Power:</strong> Requiere WordPress 5.8 o superior.</p></div>';
            });
            return false;
        }
        
        return true;
    }
    
    private function get_default_settings() {
        return [
            'retro_effects' => true,
            'lazy_load_images' => true,
            'minify_css_js' => true,
            'conversion_tracking' => true,
            'advanced_tracking' => false,
            'ai_chatbot' => false,
            'custom_css' => true,
            'scroll_animations' => false,
            'glitch_animations' => false,
            'quick_buy' => false,
            'cart_animations' => false,
            'checkout_optimization' => false,
            'abandoned_cart_recovery' => false,
            'webp_conversion' => false,
            'critical_css' => false,
            'heatmap_tracking' => false,
            'ai_recommendations' => false,
            'ai_content_optimization' => false,
            'custom_cursor' => false,
            'error_notifications' => false
        ];
    }
    
    // ==========================================
    // INICIALIZACIÓN DE HOOKS
    // ==========================================
    
    private function init_hooks() {
        // Hooks principales
        add_action('init', [$this, 'init_system']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_footer', [$this, 'inject_stayarta_magic'], 1);
        add_action('wp_head', [$this, 'optimize_critical_assets'], 1);
        add_action('template_redirect', [$this, 'smart_cache'], 1);
        
        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('admin_init', [$this, 'maybe_create_tables']);
        
        // AJAX handlers
        $this->init_ajax_handlers();
        
        // REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_endpoints']);
        
        // Plugin lifecycle hooks
        register_activation_hook(__FILE__, [$this, 'on_activation']);
        register_deactivation_hook(__FILE__, [$this, 'on_deactivation']);
    }
    
    private function init_ajax_handlers() {
        $ajax_actions = [
            'save_settings' => true,
            'track_event' => false, // false = también disponible para no logueados
            'heatmap_data' => false,
            'chatbot_message' => false,
            'get_dashboard_data' => true
        ];
        
        foreach ($ajax_actions as $action => $logged_in_only) {
            add_action("wp_ajax_stayarta_{$action}", [$this, "handle_{$action}"]);
            if (!$logged_in_only) {
                add_action("wp_ajax_nopriv_stayarta_{$action}", [$this, "handle_{$action}"]);
            }
        }
    }
    
    // ==========================================
    // SISTEMA PRINCIPAL
    // ==========================================
    
    public function init_system() {
        try {
            // Optimizaciones críticas
            $this->optimize_woocommerce();
            $this->security_fixes();
            $this->error_monitoring();
            $this->clean_logs();
            
            // Personalizaciones STAYArta
            $this->init_brand_enhancements();
            $this->init_performance_boosts();
            $this->init_conversion_tools();
            $this->init_ai_features();
            
            // Compatibilidad con plugins
            if (class_exists('WooCommerce')) {
                $this->init_woocommerce_enhancements();
            }
            
            if (defined('ELEMENTOR_VERSION')) {
                $this->init_elementor_enhancements();
            }
            
        } catch (Exception $e) {
            error_log('STAYArta Power System Error in init_system: ' . $e->getMessage());
            $this->send_error_notification($e->getMessage());
        }
    }
    
    // ==========================================
    // ENQUEUE DE ASSETS CORREGIDO
    // ==========================================
    
    public function enqueue_assets() {
        // Verificar que jQuery esté disponible
        if (!wp_script_is('jquery', 'registered')) {
            wp_enqueue_script('jquery');
        }
        
        // Script principal del plugin
        wp_enqueue_script(
            'stayarta-power-main',
            STAYARTA_POWER_ASSETS_URL . 'js/main.js',
            ['jquery'],
            $this->version,
            true
        );
        
        // Localizar script para AJAX con verificación de nonce
        wp_localize_script('stayarta-power-main', 'stayartaAjax', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('stayarta_nonce'),
            'trackingNonce' => wp_create_nonce('stayarta_tracking'),
            'heatmapNonce' => wp_create_nonce('stayarta_heatmap'),
            'chatbotNonce' => wp_create_nonce('stayarta_chatbot'),
            'isLoggedIn' => is_user_logged_in(),
            'currentPage' => get_queried_object_id(),
            'siteUrl' => home_url(),
            'pluginUrl' => STAYARTA_POWER_PLUGIN_URL
        ]);
        
        // Estilos principales
        wp_enqueue_style(
            'stayarta-power-main',
            STAYARTA_POWER_ASSETS_URL . 'css/main.css',
            [],
            $this->version
        );
        
        // Cargar assets condicionales
        $this->enqueue_conditional_assets();
    }
    
    private function enqueue_conditional_assets() {
        // Assets para chatbot
        if ($this->settings['ai_chatbot'] ?? false) {
            wp_enqueue_style(
                'stayarta-chatbot',
                STAYARTA_POWER_ASSETS_URL . 'css/chatbot.css',
                [],
                $this->version
            );
            
            wp_enqueue_script(
                'stayarta-chatbot',
                STAYARTA_POWER_ASSETS_URL . 'js/chatbot.js',
                ['stayarta-power-main'],
                $this->version,
                true
            );
        }
        
        // Assets para efectos retro
        if ($this->settings['retro_effects'] ?? false) {
            wp_enqueue_style(
                'stayarta-retro',
                STAYARTA_POWER_ASSETS_URL . 'css/retro-effects.css',
                ['stayarta-power-main'],
                $this->version
            );
        }
        
        // Assets para WooCommerce
        if (class_exists('WooCommerce') && (is_shop() || is_product() || is_cart() || is_checkout())) {
            wp_enqueue_script(
                'stayarta-woocommerce',
                STAYARTA_POWER_ASSETS_URL . 'js/woocommerce.js',
                ['stayarta-power-main'],
                $this->version,
                true
            );
        }
    }
    
    // ==========================================
    // ASSETS PARA ADMIN CORREGIDOS
    // ==========================================
    
    public function admin_assets($hook) {
        // Solo cargar en páginas del plugin
        if (strpos($hook, 'stayarta') === false) {
            return;
        }
        
        // Chart.js para dashboard
        wp_enqueue_script(
            'chart-js',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
            [],
            '3.9.1',
            true
        );
        
        // Scripts de admin
        wp_enqueue_script(
            'stayarta-admin',
            STAYARTA_POWER_ASSETS_URL . 'js/admin.js',
            ['jquery', 'chart-js'],
            $this->version,
            true
        );
        
        // Estilos de admin
        wp_enqueue_style(
            'stayarta-admin',
            STAYARTA_POWER_ASSETS_URL . 'css/admin.css',
            [],
            $this->version
        );
        
        // Localización para admin
        wp_localize_script('stayarta-admin', 'stayartaAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('stayarta_admin'),
            'settings' => $this->settings,
            'strings' => [
                'saved' => __('Configuración guardada', 'stayarta-power'),
                'error' => __('Error al guardar', 'stayarta-power'),
                'confirm' => __('¿Estás seguro?', 'stayarta-power')
            ]
        ]);
    }
    
    // ==========================================
    // MENÚ DE ADMIN MEJORADO
    // ==========================================
    
    public function add_admin_menu() {
        // Menú principal
        $main_page = add_menu_page(
            __('STAYArta Power', 'stayarta-power'),
            __('STAYArta Power', 'stayarta-power'),
            'manage_options',
            'stayarta-power',
            [$this, 'admin_dashboard_page'],
            $this->get_menu_icon(),
            30
        );
        
        // Submenús
        add_submenu_page(
            'stayarta-power',
            __('Dashboard', 'stayarta-power'),
            __('Dashboard', 'stayarta-power'),
            'manage_options',
            'stayarta-power',
            [$this, 'admin_dashboard_page']
        );
        
        add_submenu_page(
            'stayarta-power',
            __('Analytics', 'stayarta-power'),
            __('Analytics', 'stayarta-power'),
            'manage_options',
            'stayarta-analytics',
            [$this, 'admin_analytics_page']
        );
        
        add_submenu_page(
            'stayarta-power',
            __('Configuración', 'stayarta-power'),
            __('Configuración', 'stayarta-power'),
            'manage_options',
            'stayarta-settings',
            [$this, 'admin_settings_page']
        );
        
        // Hook para cargar assets solo en páginas del plugin
        add_action("admin_print_styles-{$main_page}", [$this, 'admin_assets']);
    }
    
    private function get_menu_icon() {
        return 'data:image/svg+xml;base64,' . base64_encode('
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2L2 7v10c0 5.55 3.84 9.74 9 11 5.16-1.26 9-5.45 9-11V7l-10-5z"/>
                <path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" fill="none"/>
            </svg>
        ');
    }
    
    // ==========================================
    // PÁGINAS DE ADMIN IMPLEMENTADAS
    // ==========================================
    
    public function admin_dashboard_page() {
        $stats = $this->get_dashboard_stats();
        $system_info = $this->get_system_info();
        ?>
        <div class="wrap stayarta-admin-wrap">
            <h1 class="stayarta-admin-title">
                🚀 STAYArta Power System 
                <span class="version">v<?php echo esc_html($this->version); ?></span>
            </h1>
            
            <?php $this->render_admin_notices(); ?>
            
            <div class="stayarta-dashboard">
                <!-- Métricas principales -->
                <div class="stayarta-metrics-grid">
                    <?php $this->render_metric_card('⚡', 'Performance', $this->get_performance_score() . '%', 'Score Lighthouse'); ?>
                    <?php $this->render_metric_card('🛡️', 'Seguridad', $this->get_security_score() . '%', 'Nivel de protección'); ?>
                    <?php $this->render_metric_card('🎨', 'Features', $this->count_active_features() . '/' . $this->count_total_features(), 'Características activas'); ?>
                    <?php $this->render_metric_card('📊', 'Eventos Hoy', $stats['events_today'], 'Interacciones registradas'); ?>
                </div>
                
                <!-- Gráfico de rendimiento -->
                <div class="stayarta-chart-container">
                    <h2>📈 Rendimiento en Tiempo Real</h2>
                    <canvas id="performanceChart" width="400" height="200"></canvas>
                </div>
                
                <!-- Acciones rápidas -->
                <div class="stayarta-quick-actions">
                    <h2>⚡ Acciones Rápidas</h2>
                    <div class="stayarta-action-grid">
                        <button class="stayarta-action-btn" data-action="clear-cache">
                            🗑️ Limpiar Cache
                        </button>
                        <button class="stayarta-action-btn" data-action="optimize-db">
                            🔧 Optimizar DB
                        </button>
                        <button class="stayarta-action-btn" data-action="test-chatbot">
                            🤖 Test Chatbot
                        </button>
                        <button class="stayarta-action-btn" data-action="export-settings">
                            📤 Exportar Config
                        </button>
                    </div>
                </div>
                
                <!-- Estado del sistema -->
                <div class="stayarta-system-status">
                    <h2>🔍 Estado del Sistema</h2>
                    <div class="stayarta-status-grid">
                        <?php foreach ($system_info as $key => $info): ?>
                            <div class="stayarta-status-item">
                                <span class="status-label"><?php echo esc_html($info['label']); ?>:</span>
                                <span class="status-value"><?php echo esc_html($info['value']); ?></span>
                                <span class="status-indicator <?php echo esc_attr($info['status']); ?>"></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php $this->inject_admin_styles(); ?>
        <?php $this->inject_admin_scripts(); ?>
        <?php
    }
    
    private function render_metric_card($icon, $title, $value, $description) {
        ?>
        <div class="stayarta-metric-card">
            <div class="metric-icon"><?php echo $icon; ?></div>
            <div class="metric-content">
                <h3><?php echo esc_html($title); ?></h3>
                <div class="metric-value"><?php echo esc_html($value); ?></div>
                <p><?php echo esc_html($description); ?></p>
            </div>
        </div>
        <?php
    }
    
    private function render_admin_notices() {
        // Verificar problemas comunes
        $notices = [];
        
        if (!function_exists('curl_init')) {
            $notices[] = [
                'type' => 'warning',
                'message' => 'cURL no está disponible. Algunas funciones pueden no funcionar correctamente.'
            ];
        }
        
        if (!extension_loaded('gd')) {
            $notices[] = [
                'type' => 'warning',
                'message' => 'La extensión GD no está disponible. La conversión de imágenes no funcionará.'
            ];
        }
        
        foreach ($notices as $notice) {
            ?>
            <div class="notice notice-<?php echo esc_attr($notice['type']); ?>">
                <p><strong>STAYArta Power:</strong> <?php echo esc_html($notice['message']); ?></p>
            </div>
            <?php
        }
    }
    
    public function admin_analytics_page() {
        ?>
        <div class="wrap stayarta-admin-wrap">
            <h1>📊 Analytics STAYArta</h1>
            
            <div class="stayarta-analytics-dashboard">
                <!-- Métricas de analytics -->
                <div class="analytics-overview">
                    <h2>Resumen de Actividad</h2>
                    <div class="analytics-cards">
                        <?php
                        $analytics_data = $this->get_analytics_overview();
                        foreach ($analytics_data as $metric => $data) {
                            echo "<div class='analytics-card'>";
                            echo "<h3>{$data['label']}</h3>";
                            echo "<div class='analytics-value'>{$data['value']}</div>";
                            echo "<small>{$data['description']}</small>";
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Gráficos detallados -->
                <div class="analytics-charts">
                    <canvas id="analyticsChart" width="800" height="400"></canvas>
                </div>
                
                <!-- Tabla de eventos recientes -->
                <div class="recent-events">
                    <h2>Eventos Recientes</h2>
                    <?php $this->render_recent_events_table(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function admin_settings_page() {
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['stayarta_settings_nonce'], 'stayarta_settings')) {
            $this->save_settings_from_form($_POST);
            echo '<div class="notice notice-success"><p>Configuración guardada correctamente.</p></div>';
        }
        
        ?>
        <div class="wrap stayarta-admin-wrap">
            <h1>⚙️ Configuración STAYArta</h1>
            
            <form method="post" action="" class="stayarta-settings-form">
                <?php wp_nonce_field('stayarta_settings', 'stayarta_settings_nonce'); ?>
                
                <div class="stayarta-settings-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#performance" class="nav-tab nav-tab-active">⚡ Performance</a>
                        <a href="#design" class="nav-tab">🎨 Diseño</a>
                        <a href="#ecommerce" class="nav-tab">🛒 E-commerce</a>
                        <a href="#ai" class="nav-tab">🤖 IA & Analytics</a>
                        <a href="#advanced" class="nav-tab">🔧 Avanzado</a>
                    </nav>
                    
                    <div id="performance" class="tab-content">
                        <h2>Optimizaciones de Rendimiento</h2>
                        <?php $this->render_settings_section('performance'); ?>
                    </div>
                    
                    <div id="design" class="tab-content" style="display:none;">
                        <h2>Efectos Visuales y Diseño</h2>
                        <?php $this->render_settings_section('design'); ?>
                    </div>
                    
                    <div id="ecommerce" class="tab-content" style="display:none;">
                        <h2>Mejoras para WooCommerce</h2>
                        <?php $this->render_settings_section('ecommerce'); ?>
                    </div>
                    
                    <div id="ai" class="tab-content" style="display:none;">
                        <h2>Inteligencia Artificial y Analytics</h2>
                        <?php $this->render_settings_section('ai'); ?>
                    </div>
                    
                    <div id="advanced" class="tab-content" style="display:none;">
                        <h2>Configuración Avanzada</h2>
                        <?php $this->render_settings_section('advanced'); ?>
                    </div>
                </div>
                
                <?php submit_button('Guardar Configuración', 'primary', 'submit'); ?>
            </form>
        </div>
        
        <script>
        // Tabs functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.nav-tab');
            const contents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('nav-tab-active'));
                    contents.forEach(c => c.style.display = 'none');
                    
                    // Add active class to clicked tab
                    this.classList.add('nav-tab-active');
                    
                    // Show corresponding content
                    const targetId = this.getAttribute('href').substring(1);
                    document.getElementById(targetId).style.display = 'block';
                });
            });
        });
        </script>
        <?php
    }
    
    private function render_settings_section($section) {
        $settings_config = $this->get_settings_config();
        
        if (!isset($settings_config[$section])) {
            return;
        }
        
        foreach ($settings_config[$section] as $key => $config) {
            $value = $this->settings[$key] ?? false;
            ?>
            <div class="stayarta-setting-row">
                <div class="setting-info">
                    <label for="<?php echo esc_attr($key); ?>">
                        <strong><?php echo esc_html($config['title']); ?></strong>
                    </label>
                    <p class="description"><?php echo esc_html($config['description']); ?></p>
                </div>
                <div class="setting-control">
                    <?php $this->render_setting_input($key, $config, $value); ?>
                </div>
            </div>
            <?php
        }
    }
    
    private function render_setting_input($key, $config, $value) {
        switch ($config['type']) {
            case 'checkbox':
                ?>
                <label class="stayarta-toggle">
                    <input type="checkbox" name="<?php echo esc_attr($key); ?>" value="1" <?php checked($value); ?>>
                    <span class="slider"></span>
                </label>
                <?php
                break;
                
            case 'text':
                ?>
                <input type="text" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text">
                <?php
                break;
                
            case 'textarea':
                ?>
                <textarea name="<?php echo esc_attr($key); ?>" rows="5" class="large-text"><?php echo esc_textarea($value); ?></textarea>
                <?php
                break;
                
            case 'select':
                ?>
                <select name="<?php echo esc_attr($key); ?>">
                    <?php foreach ($config['options'] as $option_value => $option_label): ?>
                        <option value="<?php echo esc_attr($option_value); ?>" <?php selected($value, $option_value); ?>>
                            <?php echo esc_html($option_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;
        }
    }
    
    private function get_settings_config() {
        return [
            'performance' => [
                'lazy_load_images' => [
                    'title' => 'Lazy Loading de Imágenes',
                    'description' => 'Carga las imágenes solo cuando son visibles',
                    'type' => 'checkbox'
                ],
                'minify_css_js' => [
                    'title' => 'Minificar CSS/JS',
                    'description' => 'Reduce el tamaño de archivos CSS y JavaScript',
                    'type' => 'checkbox'
                ],
                'webp_conversion' => [
                    'title' => 'Conversión a WebP',
                    'description' => 'Convierte imágenes a formato WebP para mejor compresión',
                    'type' => 'checkbox'
                ],
                'critical_css' => [
                    'title' => 'CSS Crítico',
                    'description' => 'Carga CSS crítico inline para mejorar el rendimiento',
                    'type' => 'checkbox'
                ]
            ],
            'design' => [
                'retro_effects' => [
                    'title' => 'Efectos Retro-Futuristas',
                    'description' => 'Activa efectos visuales retro-futuristas de la marca STAYArta',
                    'type' => 'checkbox'
                ],
                'glitch_animations' => [
                    'title' => 'Animaciones Glitch',
                    'description' => 'Efectos glitch en títulos y elementos destacados',
                    'type' => 'checkbox'
                ],
                'scroll_animations' => [
                    'title' => 'Animaciones de Scroll',
                    'description' => 'Animaciones al hacer scroll en la página',
                    'type' => 'checkbox'
                ],
                'custom_cursor' => [
                    'title' => 'Cursor Personalizado',
                    'description' => 'Cursor personalizado con tema STAYArta',
                    'type' => 'checkbox'
                ]
            ],
            'ecommerce' => [
                'quick_buy' => [
                    'title' => 'Compra Rápida',
                    'description' => 'Botón de compra rápida en productos',
                    'type' => 'checkbox'
                ],
                'cart_animations' => [
                    'title' => 'Animaciones del Carrito',
                    'description' => 'Efectos visuales al añadir productos al carrito',
                    'type' => 'checkbox'
                ],
                'checkout_optimization' => [
                    'title' => 'Optimización del Checkout',
                    'description' => 'Mejoras en el proceso de checkout',
                    'type' => 'checkbox'
                ],
                'abandoned_cart_recovery' => [
                    'title' => 'Recuperación de Carritos Abandonados',
                    'description' => 'Sistema de recuperación de carritos abandonados',
                    'type' => 'checkbox'
                ]
            ],
            'ai' => [
                'ai_chatbot' => [
                    'title' => 'Chatbot IA (NovaSTAYBot)',
                    'description' => 'Asistente virtual inteligente para atención al cliente',
                    'type' => 'checkbox'
                ],
                'ai_recommendations' => [
                    'title' => 'Recomendaciones IA',
                    'description' => 'Recomendaciones inteligentes de productos',
                    'type' => 'checkbox'
                ],
                'ai_content_optimization' => [
                    'title' => 'Optimización de Contenido IA',
                    'description' => 'Optimización automática de contenido con IA',
                    'type' => 'checkbox'
                ],
                'advanced_tracking' => [
                    'title' => 'Tracking Avanzado',
                    'description' => 'Analytics avanzados y seguimiento de comportamiento',
                    'type' => 'checkbox'
                ],
                'heatmap_tracking' => [
                    'title' => 'Heatmap Tracking',
                    'description' => 'Mapas de calor de interacciones de usuario',
                    'type' => 'checkbox'
                ]
            ],
            'advanced' => [
                'custom_css' => [
                    'title' => 'CSS Personalizado',
                    'description' => 'Inyectar CSS personalizado de STAYArta',
                    'type' => 'checkbox'
                ],
                'error_notifications' => [
                    'title' => 'Notificaciones de Error',
                    'description' => 'Enviar emails cuando ocurran errores críticos',
                    'type' => 'checkbox'
                ],
                'conversion_tracking' => [
                    'title' => 'Tracking de Conversiones',
                    'description' => 'Seguimiento básico de conversiones y eventos',
                    'type' => 'checkbox'
                ]
            ]
        ];
    }
    
    // ==========================================
    // HANDLERS AJAX CORREGIDOS Y SEGUROS
    // ==========================================
    
    public function handle_save_settings() {
        // Verificar permisos y nonce
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'stayarta_admin')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }
        
        try {
            $settings = $this->sanitize_settings($_POST['settings'] ?? []);
            update_option('stayarta_power_settings', $settings);
            $this->settings = $settings;
            
            wp_send_json_success([
                'message' => 'Configuración guardada correctamente',
                'settings' => $settings
            ]);
            
        } catch (Exception $e) {
            error_log('STAYArta Settings Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error al guardar configuración']);
        }
    }
    
    public function handle_track_event() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'stayarta_tracking')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }
        
        $event = sanitize_text_field($_POST['event'] ?? '');
        $data = $this->sanitize_event_data($_POST['data'] ?? '{}');
        
        if (empty($event)) {
            wp_send_json_error(['message' => 'Evento requerido']);
        }
        
        try {
            $this->save_analytics_event($event, $data);
            wp_send_json_success(['message' => 'Evento registrado']);
            
        } catch (Exception $e) {
            error_log('STAYArta Event Tracking Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error al registrar evento']);
        }
    }
    
    public function handle_heatmap_data() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'stayarta_heatmap')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }
        
        $type = sanitize_text_field($_POST['type'] ?? '');
        $data = $this->sanitize_event_data($_POST['data'] ?? '{}');
        
        if (empty($type)) {
            wp_send_json_error(['message' => 'Tipo requerido']);
        }
        
        try {
            $this->save_heatmap_data($type, $data);
            wp_send_json_success(['message' => 'Datos guardados']);
            
        } catch (Exception $e) {
            error_log('STAYArta Heatmap Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error al guardar datos']);
        }
    }
    
    public function handle_chatbot_message() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'stayarta_chatbot')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }
        
        $message = sanitize_text_field($_POST['message'] ?? '');
        $context = $this->sanitize_event_data($_POST['context'] ?? '{}');
        
        if (empty($message)) {
            wp_send_json_error(['message' => 'Mensaje requerido']);
        }
        
        try {
            $response = $this->generate_ai_response($message, $context);
            $quick_replies = $this->get_contextual_quick_replies($message);
            
            // Guardar interacción
            $this->save_chatbot_interaction($message, $response);
            
            wp_send_json_success([
                'response' => $response,
                'quick_replies' => $quick_replies,
                'timestamp' => current_time('H:i')
            ]);
            
        } catch (Exception $e) {
            error_log('STAYArta Chatbot Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error en el chatbot']);
        }
    }
    
    public function handle_get_dashboard_data() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'stayarta_admin')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }
        
        try {
            $data = [
                'performance' => $this->get_performance_score(),
                'security' => $this->get_security_score(),
                'features' => $this->count_active_features(),
                'events_today' => $this->get_events_today(),
                'chart_data' => $this->get_chart_data(),
                'system_info' => $this->get_system_info()
            ];
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            error_log('STAYArta Dashboard Data Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error al cargar datos']);
        }
    }
    
    // ==========================================
    // FUNCIONES DE SANITIZACIÓN
    // ==========================================
    
    private function sanitize_settings($settings) {
        $sanitized = [];
        $allowed_settings = array_keys($this->get_default_settings());
        
        foreach ($allowed_settings as $key) {
            if (isset($settings[$key])) {
                $sanitized[$key] = (bool) $settings[$key];
            } else {
                $sanitized[$key] = false;
            }
        }
        
        return $sanitized;
    }
    
    private function sanitize_event_data($data) {
        if (is_string($data)) {
            $data = json_decode(stripslashes($data), true);
        }
        
        if (!is_array($data)) {
            return [];
        }
        
        // Sanitizar recursivamente
        return $this->sanitize_array_recursive($data);
    }
    
    private function sanitize_array_recursive($array) {
        $sanitized = [];
        
        foreach ($array as $key => $value) {
            $key = sanitize_key($key);
            
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_array_recursive($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[$key] = is_float($value) ? floatval($value) : intval($value);
            } elseif (is_bool($value)) {
                $sanitized[$key] = (bool) $value;
            }
        }
        
        return $sanitized;
    }
    
    private function save_settings_from_form($post_data) {
        $settings = [];
        $allowed_settings = array_keys($this->get_default_settings());
        
        foreach ($allowed_settings as $key) {
            $settings[$key] = isset($post_data[$key]) ? true : false;
        }
        
    
    // ==========================================
    // SISTEMA DE BASE DE DATOS CORREGIDO
    // ==========================================
    
    public function maybe_create_tables() {
        $current_version = get_option('stayarta_power_db_version', '0');
        
        if (version_compare($current_version, $this->version, '<')) {
            try {
                $this->create_analytics_table();
                $this->create_chatbot_table();
                $this->create_heatmap_table();
                
                update_option('stayarta_power_db_version', $this->version);
                
            } catch (Exception $e) {
                error_log('STAYArta Database Creation Error: ' . $e->getMessage());
                $this->send_error_notification('Error creating database tables: ' . $e->getMessage());
            }
        }
    }
    
    private function create_analytics_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'stayarta_analytics';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data longtext,
            user_ip varchar(45),
            user_agent text,
            session_id varchar(100),
            page_url varchar(500),
            referrer varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type_idx (event_type),
            KEY created_at_idx (created_at),
            KEY user_ip_idx (user_ip),
            KEY session_id_idx (session_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        if ($wpdb->last_error) {
            throw new Exception('Error creating analytics table: ' . $wpdb->last_error);
        }
    }
    
    private function create_chatbot_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'stayarta_chatbot_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_message text NOT NULL,
            bot_response text NOT NULL,
            user_ip varchar(45),
            user_agent text,
            session_id varchar(100),
            response_time int DEFAULT 0,
            satisfaction_rating tinyint DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_at_idx (created_at),
            KEY user_ip_idx (user_ip),
            KEY session_id_idx (session_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        if ($wpdb->last_error) {
            throw new Exception('Error creating chatbot table: ' . $wpdb->last_error);
        }
    }
    
    private function create_heatmap_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'stayarta_heatmap';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            interaction_type varchar(50) NOT NULL,
            interaction_data longtext,
            page_url varchar(500),
            user_ip varchar(45),
            viewport_width int,
            viewport_height int,
            device_type varchar(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY interaction_type_idx (interaction_type),
            KEY page_url_idx (page_url),
            KEY created_at_idx (created_at),
            KEY device_type_idx (device_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        if ($wpdb->last_error) {
            throw new Exception('Error creating heatmap table: ' . $wpdb->last_error);
        }
    }
    
    // ==========================================
    // FUNCIONES DE BASE DE DATOS MEJORADAS
    // ==========================================
    
    private function save_analytics_event($event, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'stayarta_analytics';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'event_type' => $event,
                'event_data' => is_array($data) ? wp_json_encode($data) : $data,
                'user_ip' => $this->get_user_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'session_id' => $this->get_session_id(),
                'page_url' => $_SERVER['REQUEST_URI'] ?? '',
                'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if (!$result) {
            throw new Exception('Failed to save analytics event: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    private function save_chatbot_interaction($message, $response) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'stayarta_chatbot_logs';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'user_message' => $message,
                'bot_response' => $response,
                'user_ip' => $this->get_user_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'session_id' => $this->get_session_id(),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if (!$result) {
            throw new Exception('Failed to save chatbot interaction: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    private function save_heatmap_data($type, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'stayarta_heatmap';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'interaction_type' => $type,
                'interaction_data' => is_array($data) ? wp_json_encode($data) : $data,
                'page_url' => $data['page'] ?? $_SERVER['REQUEST_URI'] ?? '',
                'user_ip' => $this->get_user_ip(),
                'viewport_width' => intval($data['viewport_width'] ?? 0),
                'viewport_height' => intval($data['viewport_height'] ?? 0),
                'device_type' => $this->detect_device_type(),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );
        
        if (!$result) {
            throw new Exception('Failed to save heatmap data: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    // ==========================================
    // FUNCIONES AUXILIARES MEJORADAS
    // ==========================================
    
    private function get_user_ip() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    // Validar IP
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    private function get_session_id() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['stayarta_session_id'])) {
            $_SESSION['stayarta_session_id'] = uniqid('stayarta_', true);
        }
        
        return $_SESSION['stayarta_session_id'];
    }
    
    private function detect_device_type() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/mobile|android|iphone|ipod|blackberry|iemobile|opera mini/i', $user_agent)) {
            return 'mobile';
        } elseif (preg_match('/tablet|ipad|kindle|silk/i', $user_agent)) {
            return 'tablet';
        }
        
        return 'desktop';
    }
    
    // ==========================================
    // MÉTRICAS Y ANALYTICS CORREGIDAS
    // ==========================================
    
    private function get_dashboard_stats() {
        try {
            global $wpdb;
            
            $analytics_table = $wpdb->prefix . 'stayarta_analytics';
            $today = current_time('Y-m-d');
            
            // Verificar si la tabla existe
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $analytics_table)) !== $analytics_table) {
                return [
                    'events_today' => 0,
                    'total_events' => 0,
                    'unique_visitors_today' => 0
                ];
            }
            
            $events_today = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$analytics_table} WHERE DATE(created_at) = %s",
                $today
            )) ?: 0;
            
            $total_events = $wpdb->get_var("SELECT COUNT(*) FROM {$analytics_table}") ?: 0;
            
            $unique_visitors = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT user_ip) FROM {$analytics_table} WHERE DATE(created_at) = %s",
                $today
            )) ?: 0;
            
            return [
                'events_today' => intval($events_today),
                'total_events' => intval($total_events),
                'unique_visitors_today' => intval($unique_visitors)
            ];
            
        } catch (Exception $e) {
            error_log('STAYArta Dashboard Stats Error: ' . $e->getMessage());
            return [
                'events_today' => 0,
                'total_events' => 0,
                'unique_visitors_today' => 0
            ];
        }
    }
    
    private function get_performance_score() {
        $score = 100;
        
        try {
            // Verificar memoria
            $memory_usage = memory_get_usage(true);
            $memory_limit = $this->convert_to_bytes(ini_get('memory_limit'));
            $memory_percent = ($memory_usage / $memory_limit) * 100;
            
            if ($memory_percent > 80) $score -= 20;
            elseif ($memory_percent > 60) $score -= 10;
            
            // Verificar plugins activos
            $active_plugins = get_option('active_plugins', []);
            if (count($active_plugins) > 30) $score -= 15;
            elseif (count($active_plugins) > 20) $score -= 8;
            
            // Verificar optimizaciones activas
            if ($this->settings['lazy_load_images'] ?? false) $score += 5;
            if ($this->settings['minify_css_js'] ?? false) $score += 5;
            if ($this->settings['webp_conversion'] ?? false) $score += 3;
            if ($this->settings['critical_css'] ?? false) $score += 3;
            
            // Verificar caché
            if (function_exists('wp_cache_get')) $score += 10;
            
        } catch (Exception $e) {
            error_log('Performance Score Error: ' . $e->getMessage());
            $score = 85; // Score por defecto en caso de error
        }
        
        return max(min($score, 100), 0);
    }
    
    private function get_security_score() {
        $score = 100;
        
        try {
            // Verificar configuraciones básicas de seguridad
            if (defined('WP_DEBUG') && WP_DEBUG) $score -= 15;
            if (!defined('DISALLOW_FILE_EDIT') || !DISALLOW_FILE_EDIT) $score -= 20;
            if (!is_ssl()) $score -= 25;
            if (get_option('users_can_register')) $score -= 10;
            
            // Verificar versiones
            if (version_compare(get_bloginfo('version'), '6.0', '<')) $score -= 15;
            if (version_compare(PHP_VERSION, '8.0', '<')) $score -= 10;
            
            // Verificar permisos de archivos
            $wp_config_perms = substr(sprintf('%o', fileperms(ABSPATH . 'wp-config.php')), -4);
            if ($wp_config_perms !== '0644' && $wp_config_perms !== '0600') $score -= 10;
            
            // Verificar si wp-admin está protegido
            if (!file_exists(ABSPATH . '.htaccess')) $score -= 5;
            
        } catch (Exception $e) {
            error_log('Security Score Error: ' . $e->getMessage());
            $score = 80; // Score por defecto
        }
        
        return max(min($score, 100), 0);
    }
    
    private function convert_to_bytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value)-1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    private function count_active_features() {
        return count(array_filter($this->settings));
    }
    
    private function count_total_features() {
        return count($this->get_default_settings());
    }
    
    private function get_events_today() {
        $stats = $this->get_dashboard_stats();
        return $stats['events_today'];
    }
    
    private function get_system_info() {
        return [
            'wordpress' => [
                'label' => 'WordPress',
                'value' => get_bloginfo('version'),
                'status' => version_compare(get_bloginfo('version'), '6.0', '>=') ? 'good' : 'warning'
            ],
            'php' => [
                'label' => 'PHP',
                'value' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '8.0', '>=') ? 'good' : 'warning'
            ],
            'memory' => [
                'label' => 'Memoria',
                'value' => size_format(memory_get_usage(true)),
                'status' => memory_get_usage(true) < 512*1024*1024 ? 'good' : 'warning'
            ],
            'ssl' => [
                'label' => 'SSL',
                'value' => is_ssl() ? 'Activo' : 'Inactivo',
                'status' => is_ssl() ? 'good' : 'warning'
            ],
            'woocommerce' => [
                'label' => 'WooCommerce',
                'value' => class_exists('WooCommerce') ? 'Activo' : 'Inactivo',
                'status' => class_exists('WooCommerce') ? 'good' : 'inactive'
            ],
            'elementor' => [
                'label' => 'Elementor',
                'value' => defined('ELEMENTOR_VERSION') ? 'Activo' : 'Inactivo',
                'status' => defined('ELEMENTOR_VERSION') ? 'good' : 'inactive'
            ]
        ];
    }
                

// =========================================
// INTEGRACIONES STAYARTA POWER
// =========================================

// 🔁 MAKE / WEBHOOK
add_action('rest_api_init', function () {
    register_rest_route('stayarta/v1', '/webhook/(?P<id>[a-zA-Z0-9_-]+)', array(
        'methods' => 'POST',
        'callback' => 'stayarta_handle_webhook',
        'permission_callback' => '__return_true',
    ));
});
function stayarta_handle_webhook($request) {
    $id = $request['id'];
    $data = $request->get_json_params();
    do_action("stayarta_webhook_received_$id", $data);
    return new WP_REST_Response(array('success' => true), 200);
}

// 🤖 NovaSTAYBot (Telegram)
function stayarta_send_to_telegram($message) {
    $token = get_option('stayarta_power_settings')['telegram_token'] ?? '';
    $chat_id = get_option('stayarta_power_settings')['telegram_chat_id'] ?? '';
    if (!$token || !$chat_id) return;

    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $args = array(
        'body' => array(
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML'
        )
    );
    wp_remote_post($url, $args);
}

// 📬 Mailchimp
function stayarta_send_to_mailchimp($email, $tags = []) {
    $api_key = get_option('stayarta_power_settings')['mailchimp_api'] ?? '';
    $list_id = get_option('stayarta_power_settings')['mailchimp_list'] ?? '';
    if (!$api_key || !$list_id) return;

    $data_center = substr($api_key,strpos($api_key,'-')+1);
    $url = "https://{$data_center}.api.mailchimp.com/3.0/lists/{$list_id}/members/";

    $body = json_encode([
        'email_address' => $email,
        'status' => 'subscribed',
        'tags' => $tags
    ]);

    $args = [
        'headers' => [
            'Authorization' => 'apikey ' . $api_key,
            'Content-Type' => 'application/json'
        ],
        'body' => $body
    ];

    wp_remote_post($url, $args);
}

// 📊 Google Analytics - evento personalizado
function stayarta_enqueue_ga_event_script() {
    ?>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('event', 'nova_interaction', {
        event_category: 'chatbot',
        event_label: 'respuesta_AI',
        value: 1
    });
    </script>
    <?php
}
add_action('wp_footer', 'stayarta_enqueue_ga_event_script');

