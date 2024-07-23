<?php
/*
Plugin Name: Chipicasa CRM
Description: Adds metaboxes to the dashboard to show registered CPTs in the system.
Version: 1.0
Author: jjmontalban
Author URI: https://jjmontalban.github.io
Plugin URI: https://github.com/jjmontalban/chipicasa-crm
Text Domain: chipicasa-crm
Domain Path: /languages
*/

// Hook para verificar si el plugin "Inmuebles" está activo antes de activar este plugin
register_activation_hook(__FILE__, 'chipicasa_crm_activation_check');

function chipicasa_crm_activation_check() {
    if (!is_plugin_active('inmuebles/inmuebles.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('This plugin requires the "Inmuebles" plugin to be installed and active. Please install and activate the "Inmuebles" plugin before activating this plugin.', 'chipicasa-crm'), 
            __('Chipicasa CRM Activation Error', 'chipicasa-crm'),
            array('back_link' => true)
        );
    }
}


// Hook para cargar el texto de traducción
add_action('plugins_loaded', 'chipicasa_crm_load_textdomain');

function chipicasa_crm_load_textdomain() {
    load_plugin_textdomain('chipicasa-crm', false, dirname(plugin_basename(__FILE__)) . '/languages');
}


// Hook para añadir los metaboxes al dashboard
add_action('wp_dashboard_setup', 'chipicasa_crm_add_dashboard_metaboxes');

function chipicasa_crm_add_dashboard_metaboxes() {
    $custom_post_types = ['inmueble', 'demanda', 'propietario', 'consulta', 'cita'];

    foreach ($custom_post_types as $cpt) {
        wp_add_dashboard_widget(
            'chipicasa_crm_dashboard_widget_' . $cpt,
            sprintf(__('Latest %ss registered', 'chipicasa-crm'), ucwords($cpt)),
            'chipicasa_crm_display_dashboard_widget',
            null,
            ['cpt' => $cpt]
        );
    }

     // Ocultar metaboxes del sistema y otros plugins
     remove_meta_box('dashboard_quick_press', 'dashboard', 'side');       // Borrador rápido
     remove_meta_box('dashboard_activity', 'dashboard', 'normal');        // Actividad
     remove_meta_box('dashboard_site_health', 'dashboard', 'normal');     // Salud del sitio
     remove_meta_box('dashboard_right_now', 'dashboard', 'normal');       // Ahora mismo
     remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal'); // Comentarios recientes
     remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');  // Enlaces entrantes
     remove_meta_box('dashboard_plugins', 'dashboard', 'normal');         // Plugins
     remove_meta_box('dashboard_primary', 'dashboard', 'side');           // Noticias de WordPress
     remove_meta_box('dashboard_secondary', 'dashboard', 'side');         // Otras noticias de WordPress
     remove_meta_box('duplicator_dashboard_widget', 'dashboard', 'normal'); //Duplicator

}


// Función para mostrar el contenido del metabox
function chipicasa_crm_display_dashboard_widget($post, $callback_args) {
    $cpt = $callback_args['args']['cpt'];
    $cpt_object = get_post_type_object($cpt);
    if ($cpt_object) {
        if ($cpt === 'cita') {
            // Obtener las próximas citas
            $recent_posts = obtener_proximas_citas();
        } else {
            $args = array(
                'post_type' => $cpt,
                'posts_per_page' => 5,
                'post_status' => 'publish'
            );
            $recent_posts = new WP_Query($args);
        }

        if ($recent_posts->have_posts()) {
            echo '<ul>';
            while ($recent_posts->have_posts()) {
                $recent_posts->the_post();
                $edit_link = get_edit_post_link(get_the_ID());
                $nombre = get_post_meta(get_the_ID(), 'nombre', true);
                $apellidos = get_post_meta(get_the_ID(), 'apellidos', true);
                $telefono = get_post_meta(get_the_ID(), 'telefono', true);
                $email = get_post_meta(get_the_ID(), 'email', true);

                switch ($cpt) {
                    case 'inmueble':
                        $referencia = get_post_meta(get_the_ID(), 'referencia', true);
                        echo '<li><a href="' . esc_url($edit_link) . '">' . get_the_title() . ' (' . esc_html($referencia) . ')' . '</a>';
                        echo '<br><hr></li>';
                        break;

                    case 'propietario':
                    case 'demanda':
                        $telefono = get_post_meta(get_the_ID(), 'telefono', true);
                        echo '<li><a href="' . esc_url($edit_link) . '">' . esc_html($nombre) . ' ' . esc_html($apellidos) . ' (' . esc_html($telefono) . ')' . '</a>';
                        echo '<br><hr></li>';
                        break;
                        
                    case 'consulta':
                        $mensaje_excerpt = wp_trim_words(get_post_meta(get_the_ID(), 'mensaje', true), 10, '...');
                        $fecha = get_the_date();
                        $contact_info = $telefono ? esc_html($telefono) : esc_html($email);
                        echo '<li><a href="' . esc_url($edit_link) . '">' . esc_html($nombre) . ' (' . $contact_info . ')</a> ' . esc_html($fecha);
                        echo '<br>' . esc_html($mensaje_excerpt);                    
                        echo '<br><hr></li>';                    
                        break;
                    
                    case 'cita':
                        $inmueble_id = get_post_meta(get_the_ID(), 'inmueble_id', true);
                        $demanda_id = get_post_meta(get_the_ID(), 'demanda_id', true);
                        $fecha = get_post_meta(get_the_ID(), 'fecha', true);
                        $hora = get_post_meta(get_the_ID(), 'hora', true);
                    
                        $inmueble_titulo = get_the_title($inmueble_id);
                        $demanda_nombre = get_post_meta($demanda_id, 'nombre', true);
                    
                        echo '<li><a href="' . esc_url($edit_link) . '">';
                        echo __('Demanda:', 'chipicasa-crm') . ' </a>' . esc_html($demanda_nombre) . '<br>';
                        echo __('Inmueble:', 'chipicasa-crm') . ' ' . esc_html($inmueble_titulo) . '<br>';
                        echo __('Fecha:', 'chipicasa-crm') . ' ' . date('d/m/Y', strtotime($fecha)) . ' ' . date('H:i', strtotime($hora));
                        echo '<br><hr></li>';
                        break;
                }
            }
            echo '</ul>';
        } else {
            printf('<p>%s</p>', __('No recent posts.', 'chipicasa-crm'));
        }
        wp_reset_postdata();

        printf('<p><a href="%s">%s</a></p>', admin_url('edit.php?post_type=' . $cpt), sprintf(__('View all %ss', 'chipicasa-crm'), ucwords($cpt)));
    } else {
        printf('<p>%s</p>', sprintf(__('The CPT "%s" is not registered.', 'chipicasa-crm'), $cpt));
    }
}


// Función auxiliar para obtener las próximas citas
function obtener_proximas_citas($cantidad = 5) {
    $hoy = date('Y-m-d H:i:s');
    
    $args = array(
        'post_type' => 'cita',
        'posts_per_page' => $cantidad,
        'meta_query' => array(
            array(
                'key' => 'fecha',
                'value' => $hoy,
                'compare' => '>=',
                'type' => 'DATE'
            ),
        ),
        'orderby' => 'meta_value',
        'meta_key' => 'fecha',
        'order' => 'ASC'
    );
    
    $query = new WP_Query($args);
    return $query;
}

?>