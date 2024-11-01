<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<p><?php _e( 'The table below shows your MailChimp lists and their details. If you just applied changes to your MailChimp lists, please use the following button to renew the cached lists configuration.', 'text_domain' ); ?></p>


<div id="wpcuppro-list-fetcher">
	<form method="post" action="?">
		<input type="hidden" name="_wpcuppro_action" value="empty_lists_cache" />

		<p>
			<input type="submit" value="<?php _e( 'Renew MailChimp lists', 'mailchimp-for-wp' ); ?>" class="button" />
		</p>
	</form>
</div>

<div class="wpcuppro-lists-overview">
	<?php if( empty( $lists ) ) { ?>
		<p><?php _e( 'No lists were found in your MailChimp account', 'mailchimp-for-wp' ); ?>.</p>
	<?php } else {
		printf( '<p>' . __( 'A total of %d lists were found in your MailChimp account.', 'mailchimp-for-wp' ) . '</p>', count( $lists ) );

		echo '<table class="widefat striped">';

		$headings = array(
			__( 'List Name', 'mailchimp-for-wp' ),
			__( 'ID', 'mailchimp-for-wp' ),
			__( 'Subscribers', 'mailchimp-for-wp' )
		);

		echo '<thead>';
		echo '<tr>';
		foreach( $headings as $heading ) {
			echo sprintf( '<th>%s</th>', $heading );
		}
		echo '</tr>';
		echo '</thead>';


		foreach ( $lists as $list ) {
			/** @var MC4WP_MailChimp_List $list */
			echo '<tr class="toggle">';
			echo sprintf( '<td><a href="#%s">%s</a><span class="row-actions alignright"></span></td>', $list->id, esc_html( $list->name ) );
			echo sprintf( '<td>%s</td>', esc_html( $list->id ) );
			echo sprintf( '<td>%s</td>', esc_html( $list->member_count ) );
			echo '</tr>';

			echo sprintf( '<tr class="list-details list-%s-details" style="display: none;">', $list->id );
			echo '<td colspan="3" style="padding: 0 20px 40px;">';

			// Fields
			if ( ! empty( $list->merge_fields ) ) { ?>
				<h3>Merge Fields</h3>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>Name</th>
							<th>Tag</th>
							<th>Type</th>
						</tr>
					</thead>
					<?php foreach ( $list->merge_fields as $merge_field ) { ?>
						<tr>
							<td><?php echo esc_html( $merge_field->name );
								if ( $merge_field->required ) {
									echo '<span style="color:red;">*</span>';
								} ?></td>
							<td><code><?php echo esc_html( $merge_field->tag ); ?></code></td>
							<td>
								<?php
									echo esc_html( $merge_field->field_type );
									$coices = json_decode($merge_field->options);
									if( ! empty( $coices->choices ) ) {
										echo ' (' . join( ', ', $coices->choices ) . ')';
									}
								?>

							</td>
						</tr>
					<?php } ?>
				</table>
			<?php }


			echo '</td>';
			echo '</tr>';

			?>
		<?php } // end foreach $lists
		echo '</table>';
	} // end if empty ?>
</div>
<script type="text/javascript">
jQuery(function($) {
	$( ".toggle" ).click(function() {
  $(this).next('.list-details').toggle('fast');
});


});
</script>
