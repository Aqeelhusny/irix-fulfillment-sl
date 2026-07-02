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

	private function __clone() {}

	public function __wakeup(): void {
		throw new \RuntimeException( 'Cannot unserialize a singleton.' );
	}

	/**
	 * Destroy the cached singleton instance.
	 *
	 * Intended for unit tests only so each test starts with a clean slate.
	 * @internal
	 */
	public static function _reset_instance(): void {
		self::$instance = null;
	}

	/** Override in consuming class to register hooks, etc. */
	abstract protected function boot(): void;
}
