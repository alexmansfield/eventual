<?php
/**
 * Month Content Template
 * 
 * This template contains just the calendar content without the wrapper.
 * Used for both full page display and AJAX requests.
 * 
 * @package Eventual
 */

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="eventual-month__days">
	<ul class="eventual-month__day-list" style="--rows: <?php echo $total_rows; ?>;">
		<?php for ( $i = 0; $i < $first_day_of_week; $i++ ) { ?>
			<li class='eventual-month__day eventual-month__day--empty' role='gridcell' tabindex='0'></li>
		<?php } ?>

		<?php foreach ( $period as $date ) { ?>
			<?php $day_number = $date->format( 'j' ); ?>
			<?php $iso_date = $date->format( 'Y-m-d' ); ?>
			<?php $full_date = $date->format( 'l, F j, Y' ); ?>
			
			<li class='eventual-month__day' role='gridcell' tabindex='0'>
				<time datetime='<?php echo esc_attr( $iso_date ); ?>' aria-label='<?php echo esc_attr( $full_date ); ?>'>
					<?php echo esc_html( $day_number ); ?>
				</time>
			</li>
		<?php } ?>

		<?php for ( $i = $last_day_of_week; $i < 6; $i++ ) { ?>
			<li class='eventual-month__day eventual-month__day--empty' role='gridcell' tabindex='0'></li>
		<?php } ?>
	</ul>
</div>

<div class="eventual-month__events">
	<ul class="eventual-month__event-list" style="--rows: <?php echo $total_rows; ?>;">
		<?php if ( ! empty( $events ) ) : ?>
			<?php foreach ( $events as $event ) : ?>
				<?php 
				$event_start_date_string = get_post_meta( $event->ID, $this->config['start_date_key'], true );
				$event_end_date_string = get_post_meta( $event->ID, $this->config['end_date_key'], true );
				$event_start_date = new DateTime( $event_start_date_string );
				$event_end_date = new DateTime( $event_end_date_string );
				$positions = $this->get_event_position( $event_start_date, $first_day_of_week, $event_end_date );
				?>
				<?php foreach ( $positions as $position ) : ?>
					<li class="eventual-month__event <?php echo 'eventual-month__event--link-' . $this->config['calendar_link_to_events']; ?>" style="grid-row: <?php echo esc_attr( $position['grid_row'] ); ?>; grid-column: <?php echo esc_attr( $position['grid_column'] ); ?> / span <?php echo esc_attr( $position['grid_column_span'] ); ?>;">
						<?php if ( $this->config['calendar_link_to_events'] ) : ?>
							<a href="<?php echo esc_url( get_the_permalink( $event->ID ) ); ?>">
						<?php endif; ?>
						<?php echo esc_html( $event->post_title ); ?>
						<?php if ( $this->config['calendar_link_to_events'] ) : ?>
							</a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			<?php endforeach; ?>
		<?php endif; ?>
	</ul>
</div> 