<?php
defined( 'ABSPATH' ) || die();

/**
 * Gravity Forms Webhooks integration
 *
 */
class EA_GF_Plugin {

	/**
	 * EA_GF_Plugin constructor.
	 */
	public function __construct() {
		add_filter( 'gform_tooltips', array( $this, 'add_tooltips' ) );
		add_filter( 'gform_field_settings_tabs', array( $this, 'add_field_settings_tab' ), -1, 2 );
		add_action( 'gform_field_settings_tab_content_engineawesome', array( $this, 'render_tab_content' ), 10, 2 );
		add_action( 'gform_editor_js', array( $this, 'enqueue_editor_js' ) );
		add_filter( 'gform_webhooks_request_data', array( $this, 'process_webhook_data' ), 10, 4 );
	}

	/**
	 * Adds the Engine Awesome tab to the field settings.
	 *
	 * @param array  $tabs
	 * @param object $form
	 * @return array
	 */
	public function add_field_settings_tab( $tabs, $form ) {
		$tabs[] = array(
			'id'             => 'engineawesome',
			'title'          => 'Engine Awesome',
			'toggle_classes' => array( 'ea_toggle_class' ),
			'body_classes'   => array( 'ea_body_class' ),
		);
		return $tabs;
	}

	/**
	 * Renders the Engine Awesome tab content.
	 *
	 * @param object $form
	 * @param string $tab_id
	 */
	public function render_tab_content( $form, $tab_id ) {
		?>
		<h2><?php echo esc_html__( 'Webhook Settings', 'engineawesome-gf' ); ?></h2>
		<p><?php echo esc_html__( 'These settings only apply to a webhook in Gravity Forms.', 'engineawesome-gf' ); ?></p>
		<li class="awesome_addto_setting">
			<label for="field_awesome_addto">
				<?php echo esc_html__( 'Add To Field', 'engineawesome-gf' ); ?> <?php gform_tooltip( 'awesome_addto_tooltip' ); ?>
			</label>
			<input type="text" id="field_awesome_addto" onkeyup="SetFieldProperty('awesomeAddTo', this.value);" />
			<p class="description"><?php echo esc_html__( 'Enter the field to add to.', 'engineawesome-gf' ); ?></p>
		</li>
		<li class="awesome_convert_setting">
			<label for="field_awesome_convert">
				<?php echo esc_html__( 'Convert to', 'engineawesome-gf' ); ?> <?php gform_tooltip( 'awesome_convert_tooltip' ); ?>
			</label>
			<select id="field_awesome_convert" onchange="SetFieldProperty('awesomeConvert', this.value);">
				<option value=""><?php echo esc_html__( 'Default', 'engineawesome-gf' ); ?></option>
				<option value="array"><?php echo esc_html__( 'Array', 'engineawesome-gf' ); ?></option>
				<option value="comma_delimited"><?php echo esc_html__( 'Comma delimited', 'engineawesome-gf' ); ?></option>
				<option value="space"><?php echo esc_html__( 'Space', 'engineawesome-gf' ); ?></option>
			</select>
			<p class="description"><?php echo esc_html__( 'Select how to convert the input.', 'engineawesome-gf' ); ?></p>
		</li>
		<li class="awesome_meta_key_setting">
			<label for="field_awesome_meta_key">
				<?php echo esc_html__( 'Meta Key', 'engineawesome-gf' ); ?> <?php gform_tooltip( 'awesome_meta_key_tooltip' ); ?>
			</label>
			<input type="text" id="field_awesome_meta_key" onkeyup="SetFieldProperty('awesomeMetaKey', this.value);" />
			<p class="description"><?php echo esc_html__( 'Enter a meta key.', 'engineawesome-gf' ); ?></p>
		</li>
		<?php
	}

	/**
	 * Adds tooltips for Engine Awesome custom fields.
	 *
	 * @param array $tooltips
	 * @return array
	 */
	public function add_tooltips( $tooltips ) {
		$tooltips['awesome_addto_tooltip'] = sprintf(
			'<h6>%s</h6> %s',
			__('Add To Field', 'engineawesome-gf'),
			__('Enter the field to add to. This value will be appended to the target field.', 'engineawesome-gf')
		);
		
		$tooltips['awesome_convert_tooltip'] = sprintf(
			'<h6>%s</h6> %s',
			__('Convert To', 'engineawesome-gf'),
			__('Select how to convert the input. Choose "Array" to output as an array, "Comma delimited" to output as a string, "Space" to output as a space‑separated string, or "Default" to leave the output unchanged.', 'engineawesome-gf')
		);
		
		$tooltips['awesome_meta_key_tooltip'] = sprintf(
			'<h6>%s</h6> %s',
			__('Meta Key', 'engineawesome-gf'),
			__('Enter a meta key to associate with this field.', 'engineawesome-gf')
		);
		
		return $tooltips;
	}

	/**
	 * Enqueues the editor JavaScript.
	 */
	public function enqueue_editor_js() {
		// Enqueue an external JS file instead of inline JS.
		wp_enqueue_script( 'ea-gf-editor-js', plugin_dir_url( __FILE__ ) . 'assets/js/ea-editor.js', array( 'jquery' ), '1.0', true );
	}

	/**
	 * Formats a phone number.
	 *
	 * @param string $phone
	 * @return string|null
	 */
	public static function format_phone_number( $phone ) {
		if ( empty( $phone ) ) {
			return null;
		}
		$phone = trim( $phone );
		$phone = preg_replace( '/[^\d+]/', '', $phone );
		if ( strpos( $phone, '+' ) !== 0 ) {
			if ( strlen( $phone ) === 10 ) {
				$phone = '+1' . $phone;
			} elseif ( strlen( $phone ) === 11 && substr( $phone, 0, 1 ) === '1' ) {
				$phone = '+' . $phone;
			}
		}
		if ( preg_match( '/^\+?(\d{1,3})(\d{3})(\d{3})(\d{4})$/', $phone, $matches ) ) {
			return '+' . $matches[1] . ' (' . $matches[2] . ') ' . $matches[3] . '-' . $matches[4];
		} else {
			return $phone;
		}
	}

	/**
	 * Builds a mapping of field settings.
	 *
	 * @param object $form
	 * @return array
	 */
	protected function get_field_settings( $form ) {
		$settings = array();
		foreach ( $form['fields'] as $field ) {
			$field_id = (string) $field->id;
			$settings[ $field_id ] = array(
				'meta'       => ! empty( $field->awesomeMetaKey ) ? sanitize_text_field( $field->awesomeMetaKey ) : null,
				'convert'    => ( isset( $field->awesomeConvert ) && $field->awesomeConvert !== '' ) ? sanitize_text_field( $field->awesomeConvert ) : 'default',
				'addto'      => ! empty( $field->awesomeAddTo ) ? sanitize_text_field( $field->awesomeAddTo ) : null,
				'has_inputs' => ( isset( $field->inputs ) && is_array( $field->inputs ) ),
				'type'       => $field->type,
			);
		}
		return $settings;
	}

	/**
	 * Processes complex fields (address and name).
	 *
	 * If "Add To Field" is set, groups all non-empty subfield values and returns a single computed string.
	 * Otherwise, returns an associative array keyed by the original GF subfield keys (prefixed by the meta key if provided).
	 *
	 * @param string $base Field ID.
	 * @param array  $fs   Field settings.
	 * @param array  $request_data Submitted data.
	 * @return array|string
	 */
	protected function process_complex_field( $base, $fs, $request_data ) {
		$result = array();
		if ( $fs['addto'] ) {
			$values = array();
			foreach ( $request_data as $key => $v ) {
				if ( strpos( $key, $base . '.' ) === 0 && $v !== '' && $v !== null ) {
					$values[] = sanitize_text_field( $v );
				}
			}
			if ( ! empty( $values ) ) {
				if ( 'name' === $fs['type'] ) {
					$computed = implode( ', ', $values );
				} else {
					$convert = $fs['convert'];
					switch ( $convert ) {
						case 'array':
							$computed = implode( ', ', $values );
							break;
						case 'comma_delimited':
							$computed = implode( ', ', $values );
							break;
						case 'space':
							$computed = implode( ' ', $values );
							break;
						default:
							$computed = ( count( $values ) === 1 ) ? $values[0] : implode( ', ', $values );
					}
				}
				return $computed;
			}
			return '';
		} else {
			foreach ( $request_data as $key => $v ) {
				if ( strpos( $key, $base . '.' ) === 0 && $v !== '' && $v !== null ) {
					$parts  = explode( '.', $key, 2 );
					$subkey = $parts[1];
					$result[ ( $fs['meta'] ? $fs['meta'] : $base ) . '.' . $subkey ] = sanitize_text_field( $v );
				}
			}
			return $result;
		}
	}

	/**
	 * Processes simple fields.
	 *
	 * @param string $base Field ID.
	 * @param array  $fs   Field settings.
	 * @param array  $request_data Submitted data.
	 * @return string
	 */
	protected function process_simple_field( $base, $fs, $request_data ) {
		$values = array();
		if ( $fs['has_inputs'] ) {
			foreach ( $request_data as $key => $v ) {
				$parts = explode( '.', $key );
				if ( $parts[0] == $base && $v !== '' && $v !== null ) {
					$values[] = sanitize_text_field( $v );
				}
			}
		} else {
			if ( isset( $request_data[ $base ] ) && $request_data[ $base ] !== '' && $request_data[ $base ] !== null ) {
				$values[] = sanitize_text_field( $request_data[ $base ] );
			}
		}
		if ( empty( $values ) ) {
			return '';
		}
		$convert = $fs['convert'];
		if ( $convert !== 'default' || $fs['has_inputs'] ) {
			switch ( $convert ) {
				case 'array':
					$computed = implode( ', ', $values );
					break;
				case 'comma_delimited':
					$computed = implode( ', ', $values );
					break;
				case 'space':
					$computed = implode( ' ', $values );
					break;
				default:
					$computed = ( count( $values ) === 1 ) ? $values[0] : implode( ', ', $values );
			}
		} else {
			$computed = $values[0];
		}
		if ( 'phone' === $fs['type'] ) {
			$computed = self::format_phone_number( $computed );
		}
		return $computed;
	}

	/**
	 * Processes webhook data.
	 *
	 * @param array  $request_data
	 * @param mixed  $feed
	 * @param mixed  $entry
	 * @param object $form
	 * @return array
	 */
	public function process_webhook_data( $request_data, $feed, $entry, $form ) {
		$webhook_url = rgars( $feed, 'meta/requestURL' );
		if (
			rgars( $feed, 'meta/requestBodyType' ) === 'all_fields' &&
			strpos( $webhook_url, 'n8n.engineawesome.app' ) !== false
		) {
			$field_settings   = $this->get_field_settings( $form );
			$output           = array();
			$addToAccumulator = array();
			$processed_bases  = array();

			foreach ( $form['fields'] as $field ) {
				$base = (string) $field->id;
				$fs   = $field_settings[ $base ];

				// Handle address and name fields.
				if ( 'address' === $fs['type'] || 'name' === $fs['type'] ) {
					$result = $this->process_complex_field( $base, $fs, $request_data );
					if ( $fs['addto'] ) {
						if ( '' !== $result ) {
							$addToAccumulator[ $fs['addto'] ] = isset( $addToAccumulator[ $fs['addto'] ] )
								? $addToAccumulator[ $fs['addto'] ] . "\n" . $result
								: $result;
						}
					} else {
						$output = array_merge( $output, $result );
					}
					$processed_bases[] = $base;
					continue;
				}

				// Process simple fields.
				$computed = $this->process_simple_field( $base, $fs, $request_data );
				if ( '' === $computed ) {
					$processed_bases[] = $base;
					continue;
				}
				$metaKey = ( $fs['meta'] ? $fs['meta'] : $base );
				if ( $fs['addto'] ) {
					$line     = $metaKey . ": " . $computed;
					$addtoKey = $fs['addto'];
					$addToAccumulator[ $addtoKey ] = isset( $addToAccumulator[ $addtoKey ] )
						? $addToAccumulator[ $addtoKey ] . "\n" . $line
						: $line;
				} else {
					$output[ $metaKey ] = $computed;
				}
				$processed_bases[] = $base;
			}

			foreach ( $addToAccumulator as $addtoKey => $accum ) {
				$output[ $addtoKey ] = $accum;
			}

			// Append remaining keys from original request data that are not processed.
			foreach ( $request_data as $key => $v ) {
				if ( strpos( $key, '.' ) !== false ) {
					list( $base, $rest ) = explode( '.', $key, 2 );
				} else {
					$base = $key;
				}
				if ( in_array( $base, $processed_bases, true ) ) {
					continue;
				}
				if ( ! isset( $output[ $key ] ) ) {
					$output[ $key ] = sanitize_text_field( $v );
				}
			}

			// Remove keys with empty or null values.
			foreach ( $output as $key => $v ) {
				if ( '' === $v || null === $v ) {
					unset( $output[ $key ] );
				}
			}

			return $output;
		}
		return $request_data;
	}
}

new EA_GF_Plugin();
