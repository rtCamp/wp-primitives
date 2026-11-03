<?php
/**
 * Abstract Feature.
 *
 * @package rtCamp\WPPrimitives\Contracts\Abstracts
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Interfaces\ConditionallyRegistrable;
use rtCamp\WPPrimitives\Utils\FeatureSelector;

/**
 * Class AbstractFeature
 *
 * Base class for feature-flag-gated services. Each concrete feature extends a
 * consumer-defined intermediate that supplies the shared FeatureSelector
 * registry via get_feature_registry().
 *
 * On construction the feature self-registers its slug, display name, and
 * description into the registry so the admin settings page discovers it
 * automatically. can_register() reads the same registry to decide whether
 * register_hooks() should run.
 *
 * @since 1.0.0
 */
abstract class AbstractFeature implements ConditionallyRegistrable {

	/**
	 * Return the slug that identifies this feature flag.
	 *
	 * @return string Feature flag slug.
	 */
	abstract protected function get_slug(): string;

	/**
	 * Return the shared FeatureSelector registry for this consumer.
	 *
	 * @return FeatureSelector Shared registry instance.
	 */
	abstract protected function get_feature_registry(): FeatureSelector;

	/**
	 * Return the human-readable label shown on the settings page.
	 *
	 * Defaults to a title-cased version of the slug ("author-bio" → "Author Bio").
	 * Override to provide a more descriptive name.
	 *
	 * @return string Display name.
	 */
	protected function get_name(): string {
		return ucwords( str_replace( [ '-', '_' ], ' ', $this->get_slug() ) );
	}

	/**
	 * Return the description shown beneath the label on the settings page.
	 *
	 * Defaults to empty. Override to describe what enabling this feature does.
	 *
	 * @return string Description.
	 */
	protected function get_description(): string {
		return '';
	}

	/**
	 * Constructor. Self-registers the flag into the shared registry with metadata.
	 *
	 * Name and description are passed as closures, so their translations load when
	 * the settings page reads them rather than at construction, which can run
	 * before init.
	 */
	public function __construct() {
		$this->get_feature_registry()->register(
			[
				$this->get_slug() => [
					'name'        => fn (): string => $this->get_name(),
					'description' => fn (): string => $this->get_description(),
				],
			]
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function can_register(): bool {
		return $this->get_feature_registry()->is_enabled( $this->get_slug() );
	}
}
