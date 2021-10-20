<?php
/**
 * Plugin Name: Taxonomy Sorter Example
 * Description: Example plugin to showcase how to sort posts in a taxonomy.
 * Author:      Nelio Software
 * Author URI:  https://neliosoftware.com
 * Version:     1.0.0
 */

// ===================
//  CPT & TAXONOMY
// ===================

function tse_add_help_type() {
	$labels = array(
		'name'          => __( 'Questions', 'tse' ),
		'singular_name' => __( 'Question', 'tse' ),
		'menu_name'     => __( 'Help', 'tse' ),
		'all_items'     => __( 'All Questions', 'tse' ),
		'add_new_item'  => __( 'Add New Question', 'tse' ),
		'edit_item'     => __( 'Edit Question', 'tse' ),
		'view_item'     => __( 'View Question', 'tse' ),
	);

	register_post_type( 'tse_help', array(
		'capability_type' => 'post',
		'labels'          => $labels,
		'map_meta_cap'    => true,
		'menu_icon'       => 'dashicons-welcome-learn-more',
		'public'          => true,
		'show_in_rest'    => true,
		'supports'        => [ 'title', 'editor', 'author' ],
	) );
}
add_action( 'init', 'tse_add_help_type' );

function tse_add_help_taxonomy() {
  $labels = array(
    'name'          => __( 'Topics', 'tse' ),
    'singular_name' => __( 'Topic', 'tse' ),
    'menu_name'     => __( 'Topics', 'tse' ),
    'all_items'     => __( 'All Topics', 'tse' ),
    /* ... */
  );

  register_taxonomy( 'tse_topic', [ 'tse_help' ], array(
    'hierarchical'      => true,
    'label'             => __( 'Topic', 'tse' ),
    'query_var'         => true,
    'show_admin_column' => false,
    'show_ui'           => true,
    'show_in_rest'      => true,
  ) );
}
add_action( 'init', 'tse_add_help_taxonomy' );


// ===================
//  SORTING FRONTEND
// ===================

function tse_sort_questions_in_topic( $orderby, $query ) {
  if ( ! tse_is_topic_tax_query( $query ) ) return;
  global $wpdb;
  return "{$wpdb->term_relationships}.term_order ASC";
}
add_filter( 'posts_orderby', 'tse_sort_questions_in_topic', 99, 2 );

function tse_is_topic_tax_query( $query ) {
  if ( empty( $query->tax_query ) ) return;
  if ( empty( $query->tax_query->queries ) ) return;
  return in_array(
    $query->tax_query->queries[0]['taxonomy'],
    [ 'tse_topic' ],
    true
  );
}


// ===================
//  SORTING ADMIN UI
// ===================

function tse_add_sorting_page() {
	add_submenu_page(
		'edit.php?post_type=tse_help',
		'Sort',
		'Sort',
		'edit_others_posts',
		'tse-help-question-sorter',
		'tse_render_question_sorter'
	);
}
add_action( 'admin_menu', 'tse_add_sorting_page' );

function tse_render_question_sorter() {
	printf(
		'<div class="wrap"><h1>%s</h1>',
		__( 'Sort Questions', 'tse' )
	);

	$terms = get_terms( 'tse_topic' );
	tse_render_select( $terms );
	foreach ( $terms as $term ) {
		tse_render_questions_in_term( 'tse_help', 'tse_topic', $term );
	}
	tse_render_script();

	echo '</div>';
}

function tse_render_select( $terms ) {
	echo '<select id="topic">';
	foreach ( $terms as $term ) {
		printf(
			'<option value="%s">%s</option>',
			esc_attr( $term->slug ),
			esc_html( $term->name )
		);
	}
	echo '</select>';
}

function tse_render_questions_in_term( $type, $taxonomy, $term ) {
	$style = 'max-width: 50em; padding: 1em; background: white; margin: 1em 0; display: none;';
	printf(
		'<div id="%s" class="question-set" style="%s">',
		esc_attr( "{$term->slug}-questions" ),
		esc_attr( $style )
);

	$query = new WP_Query(
		array(
			'post_type'      => $type,
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term->term_id,
					'orderby'  => 'term_order',
				),
			),
		)
	);

	printf(
		'<div class="sorted-questions-in-%d sortable">',
		$term->term_id
	);
	$style = 'background: #fafafc; border-left: 0.5em solid #0073aa; padding: 0.5em; margin-bottom: 0.5em; cursor: pointer; user-select: none;';
	while ( $query->have_posts() ) {
		$query->the_post();
		global $post;
		printf(
			'<div class="question" style="%s" data-question-id="%d">%s</div>',
			esc_attr( $style ),
			$post->ID,
			esc_html( $post->post_title )
		);
	}//end foreach
	echo '</div>';

	echo '<div style="text-align: right; padding-top: 1em;">';
	printf(
		'<input class="button save-question-order" type="button" data-term-id="%d" data-term-name="%s" value="%s" />',
		$term->term_id,
		esc_attr( $term->name ),
		esc_attr( "Save {$term->name}" )
	);
	echo '</div>';

	echo '</div>';
}

function tse_render_script() { ?>
<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script type="text/javascript">
( function() {

	const select = document.getElementById( 'topic' );
	const questionSets = [ ...document.querySelectorAll( '.question-set' ) ];
	select.addEventListener( 'change', () => {
		questionSets.forEach( ( set ) => set.style.display = 'none' );
		document.getElementById( `${ select.value }-questions` ).style.display = 'block';
	} );

	document.querySelector( '.question-set' ).style.display = 'block';

	$( '.sortable' ).sortable();

	[ ...document.querySelectorAll( '.button.save-question-order' ) ].forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			const termId = button.getAttribute( 'data-term-id' );
			const termName = button.getAttribute( 'data-term-name' );

			button.value = <?php echo wp_json_encode( "Saving %s..." ); ?>.replace( '%s', termName );
			button.disabled = true;

			const sortedQuestionIds = [ ...document.querySelectorAll( `.sorted-questions-in-${ termId } .question` ) ].map( ( el ) => el.getAttribute( 'data-question-id' ) );
			$.ajax( {
				url: ajaxurl,
				method: 'POST',
				data: {
					action: 'tse_save_tax_sorting',
					ids: sortedQuestionIds,
					termId,
				},
			} ).always( () => {
				button.value = <?php echo wp_json_encode( "Save %s" ); ?>.replace( '%s', termName );
				button.disabled = false;
			} );
		} );
	} );
} )();
</script>
<?php
}

function tse_save_tax_sorting() {
	$question_ids = isset( $_POST['ids'] ) ? $_POST['ids'] : [];
	if ( ! is_array( $question_ids ) ) {
		echo -1;
		wp_die();
	}//end if
	$question_ids = array_values( array_map( 'absint', $question_ids ) );

	$term_id = absint( $_POST['termId'] );
	if ( ! $term_id ) {
		echo -2;
		wp_die();
	}//end if

	global $wpdb;
	foreach ( $question_ids as $order => $question ) {
		++$order;
		$wpdb->update(
			$wpdb->term_relationships,
			array( 'term_order' => $order ),
			array(
				'object_id'        => $question,
				'term_taxonomy_id' => $term_id,
			)
		);
	} //end foreach
	echo 0;
	wp_die();
}
add_action( 'wp_ajax_tse_save_tax_sorting', 'tse_save_tax_sorting' );

