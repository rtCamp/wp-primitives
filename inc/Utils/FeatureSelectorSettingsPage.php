<?php
/**
 * FeatureSelectorSettingsPage utility.
 *
 * @package rtCamp\WPPrimitives\Utils
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Utils;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractSettingsPage;

/**
 * Admin settings page for a FeatureSelector registry.
 *
 * Renders one checkbox per registered flag through the Settings API. The form
 * posts to `options.php` with field names of the form
 * `{shared_option_key}[{flag_key}]`, e.g. `my_plugin_features[dark-mode]`.
 *
 * A flag whose override constant is defined is rendered as a disabled
 * checkbox, and saving the form keeps its stored value.
 *
 * Register flags on the selector before `admin_init`. The flag list is read
 * when the Settings API hooks run, so flags registered after
 * {@see FeatureSelectorSettingsPage::register_hooks()} still appear.
 *
 * @since 1.0.0
 */
abstract class FeatureSelectorSettingsPage extends AbstractSettingsPage {
	/**
	 * Return the FeatureSelector whose flags this page manages.
	 *
	 * Called from hook callbacks, not from the constructor.
	 */
	abstract protected function get_selector(): FeatureSelector;

	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return 'features';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		parent::register_hooks();

		add_action( 'admin_init', [ $this, 'register_fields' ] );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Slugifies the selector's context and prepends it to the base slug.
	 *
	 * @return non-empty-string
	 */
	protected function get_menu_slug(): string {
		$context = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $this->get_selector()->get_context() ) ), '-' );

		return '' === $context ? static::get_slug() : $context . '-' . static::get_slug();
	}

	/**
	 * {@inheritDoc}
	 *
	 * The menu slug with dashes replaced by underscores, e.g. `my_plugin_features`.
	 *
	 * @return non-empty-string
	 */
	protected function get_option_group(): string {
		return str_replace( '-', '_', $this->get_menu_slug() );
	}

	/**
	 * Return the settings-section ID every flag field is attached to.
	 */
	protected function get_section_id(): string {
		return $this->get_option_group() . '_section';
	}

	/**
	 * {@inheritDoc}
	 *
	 * Context `my-plugin` → `My Plugin Features`; empty context → `Features`.
	 */
	protected function get_page_title(): string {
		$context = $this->get_selector()->get_context();

		if ( '' === $context ) {
			return __( 'Features', 'wp-primitives' );
		}

		$package = ucwords( str_replace( [ '-', '_' ], ' ', $context ) );

		/* translators: %s: package name derived from the selector's context. */
		return sprintf( __( '%s Features', 'wp-primitives' ), $package );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_menu_title(): string {
		return $this->get_page_title();
	}

	/**
	 * {@inheritDoc}
	 *
	 * Registers the selector's shared option as an array.
	 */
	protected function get_settings(): array {
		return [
			$this->get_selector()->shared_option_key() => [
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => [],
			],
		];
	}

	/**
	 * Sanitize the feature flag array before it is saved.
	 *
	 * @param mixed $input Raw value received from the Settings API.
	 * @return array<string, bool> Sanitized flag-key => bool map.
	 */
	public function sanitize_settings( mixed $input ): array {
		$submitted = is_array( $input ) ? $input : [];

		/*
		 * `sanitize_option_{key}` also fires for FeatureSelector::enable() and
		 * disable(). Their array is already complete, and rebuilding it from
		 * checkboxes would set every unstored, default-on flag to false.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php verifies the nonce and capability before this filter runs.
		$option_page = isset( $_POST['option_page'] ) ? sanitize_text_field( wp_unslash( $_POST['option_page'] ) ) : '';

		if ( $option_page !== $this->get_option_group() ) {
			$passthrough = [];

			foreach ( $submitted as $flag_key => $value ) {
				$passthrough[ (string) $flag_key ] = (bool) $value;
			}

			return $passthrough;
		}

		$stored    = (array) get_option( $this->get_selector()->shared_option_key(), [] );
		$sanitized = [];

		foreach ( $this->get_selector()->get_registered() as $slug ) {
			$key = $this->get_selector()->flag_key( $slug );

			if ( defined( $this->get_selector()->constant_name( $slug ) ) ) {
				// Keep the stored value of a flag locked by a constant.
				if ( array_key_exists( $key, $stored ) ) {
					$sanitized[ $key ] = (bool) $stored[ $key ];
				}

				continue;
			}

			$sanitized[ $key ] = isset( $submitted[ $key ] ) && (bool) $submitted[ $key ];
		}

		return $sanitized;
	}

	/**
	 * Register one settings field per flag, all attached to a single section.
	 *
	 * @internal Hooked to `admin_init`.
	 */
	public function register_fields(): void {
		add_settings_section(
			$this->get_section_id(),
			'',
			'__return_null',
			$this->get_menu_slug()
		);

		foreach ( $this->get_selector()->get_features() as $slug => $meta ) {
			add_settings_field(
				$slug,
				esc_html( $meta['name'] ),
				[ $this, 'render_field' ],
				$this->get_menu_slug(),
				$this->get_section_id(),
				$meta
			);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		if ( ! current_user_can( $this->get_capability() ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->get_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( $this->get_option_group() );
				do_settings_sections( $this->get_menu_slug() );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render one flag's checkbox.
	 *
	 * The checked state comes from {@see FeatureSelector::is_enabled()}. When
	 * the flag's override constant is defined, the checkbox is disabled and a
	 * description names the constant.
	 *
	 * @param array{slug: string, name: string, description: string} $args Flag metadata.
	 */
	public function render_field( array $args ): void {
		$field_name    = $this->get_selector()->shared_option_key() . '[' . $this->get_selector()->flag_key( $args['slug'] ) . ']';
		$constant_name = $this->get_selector()->constant_name( $args['slug'] );
		$is_locked     = defined( $constant_name );

		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( $field_name ); ?>"
				value="1"
				<?php checked( $this->get_selector()->is_enabled( $args['slug'] ) ); ?>
				<?php disabled( $is_locked ); ?>
			/>
			<?php esc_html_e( 'Enable', 'wp-primitives' ); ?>
		</label>
		<?php if ( '' !== $args['description'] ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php if ( $is_locked ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: PHP constant name. */
					esc_html__( 'This setting is locked because the constant %s is defined.', 'wp-primitives' ),
					esc_html( $constant_name )
				);
				?>
			</p>
			<?php
		endif;
	}
}
