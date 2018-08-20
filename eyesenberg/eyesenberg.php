<?php
/*
Plugin Name: Eyesenberg
Description: Find the good old screen options as an eye in admin bar when editing a post using Gutenberg
Version: 0.1
Author: Julio Potier
Author URI: https://boiteaweb.fr
*/

add_action( 'add_admin_bar_menus', 'eyesenberg_admin_bar_menus' );
function eyesenberg_admin_bar_menus() {
	global $pagenow;
	if ( ! is_admin() || 'post.php' !== $pagenow || isset( $_REQUEST['classic-editor'] ) ) {
		return;
	}
	add_action( 'admin_bar_menu', 'eyesenberg_tools', PHP_INT_MAX - 10 );
}

function eyesenberg_get_boxes() {
	global $wp_meta_boxes;

	$boxes = [];
	foreach( [ 'side', 'normal', 'advanced' ] as $context ) {
		foreach( [ 'core', 'default', 'low', 'high' ] as $priority ) {
			if ( isset( $wp_meta_boxes['post'][ $context ][ $priority ] ) && is_array( $wp_meta_boxes['post'][ $context ][ $priority ] ) ) {
				$boxes = array_merge( $boxes, $wp_meta_boxes['post'][ $context ][ $priority ] );
			}
		}
	}
	$boxes = wp_list_pluck( $boxes, 'title', 'id' );

	unset( $boxes['submitdiv'] );
	unset( $boxes['postcustom'] );
	unset( $boxes['slugdiv'] );
	unset( $boxes['trackbacksdiv'] );
	unset( $boxes['commentsdiv'] );
	$boxes['revisionsdiv'] = __( 'Revisions' );
	$boxes['sticky'] = __( 'Sticky' );
	return $boxes;
}

function eyesenberg_tools( $wp_admin_bar ) {
	$wp_admin_bar->add_group( array(
		'id'     => 'eyesenberg-tools',
		'meta'   => array(
			'class' => 'ab-top-secondary',
		),
	) );

	$wp_admin_bar->add_node( array(
		'parent' => 'eyesenberg-tools',
		'id'     => 'eyesenberg-main',
		'title'  => '<span style="font-family:dashicons;font-size:2.5em" class="dashicons dashicons-visibility"></span>', // 1.5em
	) );

	$user_boxes   = array_flip( get_user_option( 'metaboxhidden_post' ) );
	$boxes        = eyesenberg_get_boxes();
	$icon_check   = '☑️';
	$icon_uncheck = '⬜';
	foreach ( $boxes as $key => $value ) {
		$wp_admin_bar->add_node( array(
			'parent' => 'eyesenberg-main',
			'id'     => 'eyesenberg-item-' . $key,
			'title'  =>  sprintf( '<span class="eyesenberg-item" id="eyesenberg-item-%s"><span class="eyesenberg-icon">%s</span> %s</span>', sanitize_key( $key ), isset( $user_boxes[ $key ] ) ? $icon_uncheck : $icon_check, esc_html( $value ) )
		) );
	}
}

add_action( 'admin_print_footer_scripts', 'eyesenberg_javascript' );
function eyesenberg_javascript() {
	global $pagenow, $post;
	if ( 'post.php' !== $pagenow || isset( $_REQUEST['classic-editor'] ) ) {
		return;
	}
	$user_boxes   = get_user_option( 'metaboxhidden_post' );
	$boxes        = eyesenberg_get_boxes();
	if ( isset( $boxes['formatdiv'] ) ) {
		$boxes['formatdiv'] = [ 'selector' => '.editor-post-format', 'parent_depth' => 1 ];
	}
	if ( isset( $boxes['authordiv'] ) ) {
		$boxes['authordiv'] = [ 'selector' => '#post-author-selector-1', 'parent_depth' => 1 ];
	}
	$boxes['revisionsdiv']  = [ 'selector' => 'div.components-panel__body.edit-post-last-revision__panel', 'parent_depth' => 0 ];
	$boxes['sticky']        = [ 'selector' => '#inspector-checkbox-control-1', 'parent_depth' => 3 ];

	?>
	<script type="text/javascript">
	var jsonBoxes   = <?php echo json_encode( $user_boxes ); ?>;
	var hiddenBoxes = Object.values(jsonBoxes);
	var allBoxes    = JSON.parse('<?php echo json_encode( $boxes ); ?>');
	var iconCheck   = '☑️';
	var iconUncheck = '⬜';

	$(window).bind('load', function() {
		jQuery(document).ready(function($){
			// Add the IDs
			for (var i in allBoxes ) {
				if ( allBoxes[i]!=undefined && typeof allBoxes[i] !== 'object' && $("button.components-button.components-panel__body-toggle:contains('"+allBoxes[i]+"')").length ) {
					$("button.components-button.components-panel__body-toggle:contains('"+allBoxes[i]+"')").parent().parent().attr("id", i);
				}
				if ( allBoxes[i]!=undefined && typeof allBoxes[i] === 'object' ) {
					if ( allBoxes[i].parent_depth > 0 ) {
						$(allBoxes[i].selector).parents().eq(allBoxes[i].parent_depth-1).attr('id', i);
					} else {
						$(allBoxes[i].selector).attr('id', i);
					}
				}
			}
			// Hide the boxes at load
			$("#" + hiddenBoxes.join(",#")).hide();

			// Toggle visibility on click.
			$(".eyesenberg-item").on("click", function(e) {
				var theKey = $(this).attr("id").replace("eyesenberg-item-", "");
				var theId = "#" + theKey;

				if ( $(theId).length ) {
					if ( $(theId).is(':visible') ) {
						$(theId).hide();
						$(this).find(" .eyesenberg-icon").text( iconUncheck );
						hiddenBoxes.push(theKey);
					} else {
						$(theId).show();
						$(this).find(".eyesenberg-icon").text( iconCheck );
						hiddenBoxes.splice( hiddenBoxes.indexOf(theKey), 1 );
					}
					$.post(ajaxurl, {
						action: "closed-postboxes",
						hidden: hiddenBoxes.join(","),
						closedpostboxesnonce: '<?php echo esc_js( wp_create_nonce( 'closedpostboxes' ) ); ?>',
						page: "post"
					});
				}
			});
		});
	});
	</script>
	<?php
}
