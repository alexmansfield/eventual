<?php
/**
 * Month Display Template
 * 
 * This template is used by the Eventual plugin to display month information.
 * 
 * @package Eventual
 */

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="eventual-month">
	<div class="eventual-month__header">
		<?php if ( $this->config['navigation_links'] ) : ?>
			<a href="<?php echo esc_url( $prev_url ); ?>" class="eventual-month__navigation-link eventual-month__navigation-link--previous">
				<span class="eventual-month__navigation-link-icon">&lt;</span>
			</a>
		<?php endif; ?>
		<h3 class="eventual-month__title"><?php echo $month_name; ?> <?php echo $year; ?></h3>
		<?php if ( $this->config['navigation_links'] ) : ?>
			<a href="<?php echo esc_url( $next_url ); ?>" class="eventual-month__navigation-link eventual-month__navigation-link--next">
				<span class="eventual-month__navigation-link-icon">&gt;</span>
			</a>
		<?php endif; ?>
	</div>
	<div class="eventual-month__day-names">
		<ul>
			<li>Sun</li>
			<li>Mon</li>
			<li>Tue</li>
			<li>Wed</li>
			<li>Thu</li>
			<li>Fri</li>
			<li>Sat</li>
		</ul>
	</div>

	
	<div class="eventual-month__content">
		<?php echo $this->get_month_content($atts); ?>
	</div>
</div> 