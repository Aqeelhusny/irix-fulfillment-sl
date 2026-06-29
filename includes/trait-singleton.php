<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Reusable singleton pattern.
 *
 * Usage: `use IRIXFSL_Singleton;` inside a final class, then call `ClassName::instance()`.
 */
trait IRIXFSL_Singleton {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->boot();
	}

	/** Override in consuming class to register hooks, etc. */
	abstract protected function boot(): void;
}
