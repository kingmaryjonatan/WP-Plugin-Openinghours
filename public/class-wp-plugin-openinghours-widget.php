<?php
/**
 * Class for handling calendar and widget logic
 *
 * @since      1.0.0
 * @package    Wp_Plugin_Openinghours
 * @subpackage Wp_Plugin_Openinghours/public
 * @author     Andreas Färnstrand <andreas.farnstrand@cybercom.com>
 */
class WP_Plugin_OpeningHours_Widget {


	/**
	 * Class constructor
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function __construct() {

		add_shortcode( 'opening_hours', array( $this, 'opening_hours' ) );

	}


	/**
	 * Shortcode output for opening_hours
	 *
	 * @since    1.0.0
	 * @access   public
	 *
	 * @return      string      html output for shortcode
	 */
	public function opening_hours( $atts ) {

		$attributes = shortcode_atts( array(
			'date' => date_i18n('Y-m-d'), // Default to today if no date is set
			'location' => 'all'
		), $atts );

		ob_start();
		?>
		<div class="card opening-hours-wrapper">
			<div class="card-block opening-hours-header">
				<div class="arrow left-arrow float-xs-left"><?php material_icon( 'chevron left', array('size' => '2.5em') ); ?></div>
				<div class="header float-xs-left">
					<p class="title">Öppettider i Sundsvall</p>
					<p class="date"><?php echo date_i18n('l j F', strtotime( $attributes['date'] ) ); ?></p>
					<div class="current-date"><?php echo date_i18n('Y-m-d'); ?></div>
					<div class="current-location"><?php echo $attributes['location'] ?></div>
					<div class="datepicker-wrapper">
						<input type="text" value="<? $attributes['date']?>" id="opening-hours-datepicker" />
					</div>
				</div>
				<div class="arrow right-arrow float-xs-right"><?php material_icon( 'chevron right', array('size' => '2.5em') ); ?></div>
			</div>
			<?php echo self::hours( $attributes['date'], $attributes['location'] ); ?>
			<div class="card-block opening-hours-footer">
				<a href="#" class="card-link">Visa alla öppettider i Sundsvall</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}


	/**
	 * The html output for the hours section
	 *
	 * @since    1.0.0
	 * @access   private
	 *
	 * @return      string      html
	 */
	public static function hours( $date, $wanted_location ) {
		$opening_hours = self::setup_opening_hours( $date, $wanted_location );
		ob_start();
		?>
		<div class="loader"></div>
		<div class="opening-hours-widget list-group list-group-flush">
			<?php foreach( $opening_hours as $location ) : ?>
				<?php if ( is_array( $location['hours'] ) || is_string( $location['hours'] ) ) : ?>

					<div class="list-group-item">

						<div class="icon float-xs-left"><?php material_icon( 'query builder' ); ?></div>
						<div class="location-information float-xs-left">

							<?php echo self::location_information( $location ); ?>

						</div>
						<div class="clearfix"></div>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}


	/**
	 * The html output for a single location
	 *
	 * @since    1.0.0
	 * @access   private
	 *
	 * @return      string      html
	 */
	private function location_information( $location ) {
		ob_start();
		?>
		<div class="title"><a href="<?php echo get_permalink( $location['location_data']->ID ); ?>"><?php echo $location['location_data']->post_title; ?></a></div>
		<?php if ( is_array( $location['hours'] ) ) : ?>
			<?php
			$compiled_hours = array();

			foreach ( $location['hours'] as $hour ) {
				$compiled_hours []= array_shift( explode( ':', $hour['oppningstid'] ) ) . '-' . array_shift( explode( ':', $hour['stangningstid'] ) );
			}

			$phone = '';
			if( ! empty( $location['location_data']->contact_phone ) ) {
				$phone = sprintf( "<span class=\"phone\">Tel - %s</span>", $location['location_data']->contact_phone );
			}
			?>
			<div>
				<span class="hours"><?php echo implode( ', ', $compiled_hours ); ?></span>
				<?php echo $phone ?>
			</div>

		<?php else : ?>
			<div>STÄNGT</div>
		<?php endif; ?>

		<?php
		return ob_get_clean();
	}


	/**
	 * Parse location posts and their opening and close times
	 * to setup an array with compund information.
	 *
	 * @since    1.0.0
	 * @access   public
	 *
	 * @param   $check_date     the date to check against
	 *
	 * @return  array       the array with information about locations and times
	 */
	private function setup_opening_hours( $check_date, $wanted_location ) {

		$locations = WP_Plugin_OpeningHours_Posttype_Location::locations( $wanted_location );
		$opening_hours = array();

		if ( count( $locations ) > 0 ) {

			foreach ( $locations as $location ) {

				$phone = get_field( 'telefon', $location->ID );
				$email = get_field( 'e-post', $location->ID );

				$field_name = 'standard_' . date_i18n( 'w', strtotime( $check_date ) );
				$standard_opening_hours = get_field( $field_name, $location->ID );

				$opening_hours[$location->ID]['location_data'] = $location;
				$opening_hours[$location->ID]['location_data']->contact_phone = $phone;
				$opening_hours[$location->ID]['location_data']->contact_email = $email;

				if ( is_array( $standard_opening_hours ) && count( $standard_opening_hours ) > 0 ) {

					$opening_hours[$location->ID]['hours'] = $standard_opening_hours;
					$opening_hours[$location->ID]['type'] = 'standard';

				}

				$opening_hours = self::check_deviation_periods( $check_date, $location, $opening_hours );
				$opening_hours = self::check_deviation_dates( $check_date, $location, $opening_hours );

				if ( empty( $opening_hours[ $location->ID ]['type'] )|| $opening_hours[$location->ID]['closed'] == true ) {
					$opening_hours[ $location->ID ]['hours'] = 'STÄNGT';
				}


			}


		}

		return $opening_hours;

	}


	/**
	 * Check given date against calendar deviation periods.
	 *
	 * @since    1.0.0
	 * @access   private
	 *
	 * @param   $check_date     the date to check against
	 * @param   $location       the location object
	 * @param   $opening_hours  the compund array with opening hours and locations
	 *
	 * @return  array       the array with information about locations and times
	 */
	private function check_deviation_periods( $check_date, $location, $opening_hours ) {

		$deviation_periods = get_field( 'avvikelse', $location->ID );
		$day_number = date_i18n('w', strtotime( $check_date ) );

		if ( is_array( $deviation_periods ) && count( $deviation_periods ) > 0 ) {

			foreach ( $deviation_periods as $deviation ) {

				// Is this a date in the period
				if ( self::between_dates( $check_date, $deviation['startdatum'], $deviation['slutdatum'] ) ) {

					if( is_array( $deviation['veckodag'] ) && count( $deviation['veckodag'] ) > 0 ) {

						// Is the date in given weekday of the period
						if( in_array( $day_number, $deviation['veckodag'] ) ) {

							if( $opening_hours[$location->ID]['type'] != 'single' ) {

								$opening_hours[$location->ID]['hours'] = $deviation['tider'];
								$opening_hours[$location->ID]['type'] = 'period';

								//echo '<pre>' . print_r( $deviation, true ) . '</pre>';
								//die();
								if ( $deviation['typ'] === 'stangt' ) {
									$opening_hours[$location->ID]['closed'] = true;
								} else {
									$opening_hours[$location->ID]['closed'] = false;
								}

							}

						}

					} else { // No weekdays given. Check all days unless a single date has overriden

						if( $opening_hours[$location->ID]['type'] != 'single' ) {

							$opening_hours[$location->ID]['hours'] = $deviation['tider'];
							$opening_hours[$location->ID]['type'] = 'period';

							if ( $deviation['typ'] === 'stangt' ) {
								$opening_hours[$location->ID]['closed'] = true;
							} else {
								$opening_hours[$location->ID]['closed'] = false;
							}

						}

					}

				}

			}

		}

		return $opening_hours;

	}


	/**
	 * Check given date against calendar deviation dates.
	 *
	 * @since    1.0.0
	 * @access   private
	 *
	 * @param   $check_date     the date to check against
	 * @param   $location       the location object
	 * @param   $opening_hours  the compund array with opening hours and locations
	 *
	 * @return  array       the array with information about locations and times
	 */
	private function check_deviation_dates( $check_date, $location, $opening_hours ) {

		$deviation_dates = get_field( 'avvikande_datum', $location->ID );
		if ( is_array( $deviation_dates ) && count( $deviation_dates ) > 0 ) {

			foreach ( $deviation_dates as $deviation ) {

				if ( $check_date == $deviation['datum'] ) {

					//echo '<pre>' . print_r( $deviation, true ) . '</pre>';
					// Found a deviation
					$opening_hours[$location->ID]['hours'] = $deviation['tider'];
					$opening_hours[$location->ID]['type'] = 'single';

					if ( $deviation['typ'] === 'stangt' ) {
						$opening_hours[$location->ID]['closed'] = true;
					} else {
						$opening_hours[$location->ID]['closed'] = false;
					}

				}

			}

		}

		return $opening_hours;

	}


	/**
	 * Check if a date falls bestween two dates
	 *
	 * @since    1.0.0
	 * @access   private
	 *
	 * @param   $check_date     the date to check against
	 * @param   $first_date
	 * @param $second_date
	 *
	 * @return bool
	 */
	private function between_dates( $check_date, $first_date, $second_date ) {

		if (  $check_date <= $second_date && $check_date >= $first_date ) return true;
		return false;

	}

}