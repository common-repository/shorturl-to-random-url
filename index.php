<?php

/*

Plugin name: Shorturl to random url
Description: This plugin will redirect users from an url to another
Author: Md. Sarwar-A-Kawsar
Author URI: https://fiverr.com/sa_kawsar
Version: 1.0

*/

defined('ABSPATH') or die('You cannot access to this page');

function sru_activate(){
	global $wpdb;
	$table_name = $wpdb->prefix.'url_shorter';
	if( $wpdb->get_var("SHOW TABLES LIKE ".$table_name) != $table_name ){
		$sql = "CREATE TABLE $table_name (
          id INT(9) NOT NULL AUTO_INCREMENT,
          post_id INT(9) NOT NULL,
          url LONGTEXT NOT NULL,
          value VARCHAR(100) NOT NULL,
          ratio_value VARCHAR(100) NOT NULL,
          slug VARCHAR(100) NOT NULL,
          UNIQUE KEY id (id)
     	) $charset_collate;";
     	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
     	dbDelta( $sql );
	}
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'sru_activate' );

function sru_cptui_register_my_cpts_url_shorter() {
	/**
	 * Post Type: URL shorter.
	 */
	$labels = array(
		"name" => __( "URL shorter", "sru" ),
		"singular_name" => __( "URL shorter", "sru" ),
	);

	$args = array(
		"label" => __( "URL shorter", "sru" ),
		"labels" => $labels,
		"description" => "",
		"public" => true,
		"publicly_queryable" => true,
		"show_ui" => true,
		"delete_with_user" => false,
		"show_in_rest" => true,
		"rest_base" => "",
		"rest_controller_class" => "WP_REST_Posts_Controller",
		"has_archive" => false,
		"show_in_menu" => true,
		"show_in_nav_menus" => true,
		"exclude_from_search" => false,
		"capability_type" => "post",
		"map_meta_cap" => true,
		"hierarchical" => false,
		"rewrite" => array( "slug" => "url_shorter", "with_front" => true ),
		"query_var" => true,
		"supports" => array( "title" ),
	);
	register_post_type( "url_shorter", $args );
}

add_action( 'init', 'sru_cptui_register_my_cpts_url_shorter' );

function sru_admin_custom_menu(){
	add_menu_page( 'URL shorter', 'URL shorter', 'manage_options', 'url_shorter', 'sru_url_shorter_callback' );
}
add_action('admin_menu','sru_admin_custom_menu');

function sru_url_shorter_callback(){
	if(isset($_POST['delete_link']) && isset($_POST['sru_nonce']) && wp_verify_nonce( $_POST['sru_nonce'], 'sru_delete_post' ) && current_user_can('administrator')):
		$post_id = sanitize_text_field(( $_POST['post_id'] ));
		wp_delete_post( $post_id );
		global $wpdb;
		$table_name = $wpdb->prefix.'url_shorter';
		$wpdb->delete($table_name,array('post_id' => $post_id));
		echo esc_html( '<div class="alert alert-danger">Link has been deleted successfully.</div>' );
	endif;

	if(isset($_POST['import_data']) && isset($_POST['sru_nonce']) && wp_verify_nonce( $_POST['sru_nonce'], 'sru_import_post' ) && current_user_can('administrator')):
		$file = $_FILES['csv_file'];
		// wp_check_filetype( $filename, $mimes = null )
		$file_name = sanitize_file_name( ( $_FILES['csv_file']['name'] ) );
		$ext = pathinfo($file_name, PATHINFO_EXTENSION);
		$file_temp_url = $file['tmp_name'];
		$file_open = fopen($file_temp_url,'r');
		if($ext == 'csv'):
			$args = array(
				'post_title' => 'Test',
				'post_type' => 'url_shorter',
				'post_status' => 'publish'
			);
			$slug = substr(md5($post_id.rand()), 0,8);
			$post_id = wp_insert_post( $args );
			$args = array(
				'ID' => $post_id,
				'post_name' => $slug,
			);
			wp_update_post( $args );
			$all_lines = [];
			$i = 0;
			while(($line = fgetcsv($file_open)) !== false):
				if(is_numeric($line[1])):
					$all_lines[] = $line;
					global $wpdb;
					$table_name = $wpdb->prefix.'url_shorter';
					$insert_id = $wpdb->insert($table_name,array(
						'post_id' => $post_id,
						'url' => $line[0],
						'value' => $line[1],
						'ratio_value' => 0,
						'slug' => $slug,
					));
					$i++;
				else:
					continue;
				endif;
			endwhile;
			if($post_id):
				if($i==0):
					wp_delete_post( $post_id );
					echo esc_html( '<div class="alert alert-danger">Invalid file format.</div>' );
				else:
					echo esc_html( '<div class="alert alert-success">Link added successfully.</div>' );
				endif;
			endif;
		else:
			echo esc_html( '<div class="alert alert-danger">Invalid file type.</div>' );
		endif;
	endif;
	?>
	<h2>Form data</h2>
	<form method="post" enctype="multipart/form-data">
		<label>Import your CSV file</label><br>
		<input type="file" name="csv_file" accept=".csv"/>
		<input type="hidden" name="sru_nonce" value="<?php echo wp_create_nonce( 'sru_import_post' ); ?>" />
		<input type="submit" class="button button-primary" name="import_data" value="Import">
	</form>
	<h2>All data</h2>
	<table class="table table-striped">
		<tr>
			<th>Link ID</th>
			<th>Link</th>
			<th>Action</th>
		</tr>
		<?php
		$args = array(
			'post_type' => 'url_shorter',
			'post_status' => 'publish',
			'posts_per_page' => -1
		);

		$query = new WP_Query( $args );
		if($query->have_posts()):
			while($query->have_posts()): $query->the_post();
		?>
		<tr>
			<td><?php the_ID(); ?></td>
			<td><a target="_blank" href="<?php echo str_replace('url_shorter/','',get_the_permalink()); ?>"><?php echo str_replace('url_shorter/','',get_the_permalink()); ?></a></td>
			<td>
				<form method="post">
					<input type="hidden" name="post_id" value="<?php the_ID(); ?>"/>
					<input type="hidden" name="sru_nonce" value="<?php echo wp_create_nonce( 'sru_delete_post' ); ?>"/>
					<button type="submit" name="delete_link" class="button button-primary">Delete</button>
				</form>
			</td>
		</tr>
		<?php
			endwhile;
		else:
			?>
			<tr>
				<td colspan="3" align="center">No link found</td>
			</tr>
			<?php
		endif;
		?>
	</table>
	<?php
}

add_action( 'init', 'sru_redirect_the_visitor' ,9999);
function sru_redirect_the_visitor(){
	global $wpdb;
	$table_name = $wpdb->prefix.'url_shorter';
	$url = end(array_filter(explode('/', $_SERVER['REDIRECT_URL'])));
	$data = $wpdb->get_results("SELECT * FROM $table_name WHERE slug='$url'");
	if(!empty($data)){
		$url_count = count($data);
		$priority = [];
		$arr = [];
		foreach ($data as $key => $value) {
			$dataset = [];
			$dataset['url'] 		= $value->url;
			$dataset['priority'] 	= '';
			$root_value = $value->value;
			$priority[$value->id] = $root_value;
			$arr[$value->id] = $dataset;
		}
		asort($priority);
		$min_value = reset($priority);
		$min_priority = [];
		foreach ($priority as $key => $value) {
			// $min_p = round($value / $min_value);
			$min_p = $value;
			$get_data = $arr[$key];
			$get_data['priority'] = $min_p;
			$arr[$key] = $get_data;
		}
		sort($arr);
		$keys = [];
		for ($i = 0; $i < count($arr); $i++) {
		     for ($u = 0; $u < $arr[$i]['priority']; $u++) {
		         $keys[] = $i;      
		     }
		}
		$url = $arr[$keys[rand(0,count($keys)-1)]]['url'];
		// echo '<pre>';
		// print_r($keys);
		// echo '</pre>';
		header("Location:".$url);
		exit();
	}
}
add_action('admin_enqueue_scripts','sru_custom_enqueue_script');
function sru_custom_enqueue_script() {
	$plugin = plugin_dir_url( __FILE__ );
	wp_enqueue_style( 'custom-bootstrap', $plugin.'css/bootstrap.min.css');
	wp_enqueue_style( 'custom-style', $plugin.'css/style.css');
}