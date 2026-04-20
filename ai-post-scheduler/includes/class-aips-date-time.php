<?php
/**
 * AIPS_DateTime — Immutable, UTC-first date/time value object.
 *
 * Every date/time in the plugin flows through this class so there is a single,
 * consistent representation.  Internal storage is always UTC.  Display methods
 * convert to the WordPress site timezone on-the-fly.
 *
 * Database columns store Unix timestamps (BIGINT UNSIGNED).  Use
 * {@see AIPS_DateTime::now()->timestamp()} when writing and
 * {@see AIPS_DateTime::fromTimestamp()} when reading.
 *
 * @package AI_Post_Scheduler
 * @since   2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_DateTime extends DateTimeImmutable {

	/* ─── Shared UTC timezone instance ──────────────────────────────── */

	/**
	 * @var DateTimeZone|null Cached UTC timezone.
	 */
	private static $utc = null;

	/**
	 * Return a shared UTC DateTimeZone instance.
	 *
	 * @return DateTimeZone
	 */
	private static function utc(): DateTimeZone {
		if ( self::$utc === null ) {
			self::$utc = new DateTimeZone( 'UTC' );
		}
		return self::$utc;
	}

	/**
	 * Wrap any DateTimeInterface as an AIPS_DateTime in the same timezone.
	 *
	 * Needed because DateTimeImmutable methods (modify, setTimezone, etc.)
	 * return the parent type on PHP < 8.3.
	 *
	 * @param DateTimeInterface $dt Source instance.
	 * @return static
	 */
	private static function wrap( DateTimeInterface $dt ): static {
		return new static(
			$dt->format( 'Y-m-d H:i:s' ),
			$dt->getTimezone() ?: self::utc()
		);
	}

	/* ─── Factory methods ───────────────────────────────────────────── */

	/**
	 * Current UTC time.
	 *
	 * @return static
	 */
	public static function now(): static {
		return new static( 'now', self::utc() );
	}

	/**
	 * Create from a Unix timestamp.
	 *
	 * @param int $ts Unix timestamp (seconds since epoch).
	 * @return static
	 */
	public static function fromTimestamp( int $ts ): static {
		$instance = new static( '@' . $ts );
		// @-format always uses +00:00 internally; normalise to named UTC.
		return new static( $instance->format( 'Y-m-d H:i:s' ), self::utc() );
	}

	/**
	 * Create from a Unix timestamp, returning null when the value
	 * represents "not set" (zero or negative).
	 *
	 * @param int $ts Unix timestamp.
	 * @return static|null
	 */
	public static function fromTimestampOrNull( int $ts ): ?static {
		return $ts > 0 ? static::fromTimestamp( $ts ) : null;
	}

	/**
	 * Parse a MySQL-format datetime string as UTC.
	 *
	 * Use this for legacy data or when reading values that are still stored
	 * as datetime strings (e.g. during migration, or from external APIs).
	 *
	 * @param string $datetime 'Y-m-d H:i:s' formatted string.
	 * @return static
	 * @throws \InvalidArgumentException If the string cannot be parsed.
	 */
	public static function fromMysql( string $datetime ): static {
		$dt = parent::createFromFormat( 'Y-m-d H:i:s', $datetime, self::utc() );
		if ( $dt === false ) {
			throw new \InvalidArgumentException(
				sprintf( 'Cannot parse MySQL datetime: %s', $datetime )
			);
		}
		return new static( $dt->format( 'Y-m-d H:i:s' ), self::utc() );
	}

	/**
	 * Parse a MySQL-format datetime, returning null on empty/invalid input.
	 *
	 * @param string|null $datetime 'Y-m-d H:i:s' or null/empty.
	 * @return static|null
	 */
	public static function fromMysqlOrNull( ?string $datetime ): ?static {
		if ( empty( $datetime ) || $datetime === '0000-00-00 00:00:00' ) {
			return null;
		}
		try {
			return static::fromMysql( $datetime );
		} catch ( \InvalidArgumentException $e ) {
			return null;
		}
	}

	/**
	 * Parse a date-only string ('Y-m-d') as midnight UTC.
	 *
	 * @param string $date 'Y-m-d' formatted string.
	 * @return static
	 */
	public static function fromDate( string $date ): static {
		return new static( $date . ' 00:00:00', self::utc() );
	}

	/* ─── Scalar output ─────────────────────────────────────────────── */

	/**
	 * Unix timestamp.
	 *
	 * @return int
	 */
	public function timestamp(): int {
		return $this->getTimestamp();
	}

	/**
	 * MySQL datetime format in UTC (for logging, diagnostics, backward compat).
	 *
	 * @return string 'Y-m-d H:i:s'
	 */
	public function toMysql(): string {
		return $this->format( 'Y-m-d H:i:s' );
	}

	/**
	 * ISO 8601 format in UTC.
	 *
	 * @return string e.g. '2024-03-15T14:30:00+00:00'
	 */
	public function toIso8601(): string {
		return $this->format( 'c' );
	}

	/* ─── Display (WordPress site timezone) ─────────────────────────── */

	/**
	 * Format using the site timezone and WordPress locale.
	 *
	 * Falls back to PHP date() when wp_date() is unavailable (unit tests).
	 *
	 * @param string $format PHP date format.  Empty = WP date + time settings.
	 * @return string
	 */
	public function toDisplay( string $format = '' ): string {
		if ( empty( $format ) ) {
			$format = $this->wp_datetime_format();
		}

		if ( function_exists( 'wp_date' ) ) {
			return wp_date( $format, $this->getTimestamp() );
		}

		// Fallback for non-WP environments (unit tests).
		return $this->format( $format );
	}

	/**
	 * Date-only display using the WordPress site date format.
	 *
	 * @return string
	 */
	public function toDateDisplay(): string {
		$format = function_exists( 'get_option' )
			? get_option( 'date_format', 'Y-m-d' )
			: 'Y-m-d';
		return $this->toDisplay( $format );
	}

	/**
	 * Time-only display using the WordPress site time format.
	 *
	 * @return string
	 */
	public function toTimeDisplay(): string {
		$format = function_exists( 'get_option' )
			? get_option( 'time_format', 'H:i' )
			: 'H:i';
		return $this->toDisplay( $format );
	}

	/**
	 * Human-readable relative time ("2 hours ago", "in 3 days").
	 *
	 * Uses WordPress's human_time_diff() when available.
	 *
	 * @param static|null $reference Compare against this time.  Default = now.
	 * @return string
	 */
	public function toHumanDiff( ?self $reference = null ): string {
		$ref_ts = $reference ? $reference->getTimestamp() : time();
		$ts     = $this->getTimestamp();

		if ( function_exists( 'human_time_diff' ) ) {
			$diff_string = human_time_diff( $ts, $ref_ts );

			if ( $ts <= $ref_ts ) {
				/* translators: %s: human-readable time difference */
				return sprintf( __( '%s ago', 'ai-post-scheduler' ), $diff_string );
			}

			/* translators: %s: human-readable time difference */
			return sprintf( __( 'in %s', 'ai-post-scheduler' ), $diff_string );
		}

		// Minimal fallback.
		$diff = abs( $ref_ts - $ts );
		if ( $diff < 60 ) {
			return __( 'just now', 'ai-post-scheduler' );
		}
		if ( $diff < 3600 ) {
			$minutes = (int) round( $diff / 60 );
			$label   = sprintf( _n( '%d minute', '%d minutes', $minutes, 'ai-post-scheduler' ), $minutes );
		} elseif ( $diff < 86400 ) {
			$hours = (int) round( $diff / 3600 );
			$label = sprintf( _n( '%d hour', '%d hours', $hours, 'ai-post-scheduler' ), $hours );
		} else {
			$days  = (int) round( $diff / 86400 );
			$label = sprintf( _n( '%d day', '%d days', $days, 'ai-post-scheduler' ), $days );
		}

		return ( $ts <= $ref_ts )
			/* translators: %s: human-readable time difference */
			? sprintf( __( '%s ago', 'ai-post-scheduler' ), $label )
			/* translators: %s: human-readable time difference */
			: sprintf( __( 'in %s', 'ai-post-scheduler' ), $label );
	}

	/* ─── Comparison helpers ────────────────────────────────────────── */

	/**
	 * Whether this time is in the past.
	 *
	 * @return bool
	 */
	public function isPast(): bool {
		return $this->getTimestamp() < time();
	}

	/**
	 * Whether this time is in the future.
	 *
	 * @return bool
	 */
	public function isFuture(): bool {
		return $this->getTimestamp() > time();
	}

	/**
	 * Whether this time falls on today in the site timezone.
	 *
	 * @return bool
	 */
	public function isToday(): bool {
		$site_tz     = $this->site_timezone();
		$this_date   = static::wrap( parent::setTimezone( $site_tz ) )->format( 'Y-m-d' );
		$today_date  = static::now()->withTimezone( $site_tz )->format( 'Y-m-d' );
		return $this_date === $today_date;
	}

	/**
	 * Whether this time is before the given time.
	 *
	 * @param static $other The time to compare with.
	 * @return bool
	 */
	public function isBefore( self $other ): bool {
		return $this->getTimestamp() < $other->getTimestamp();
	}

	/**
	 * Whether this time is after the given time.
	 *
	 * @param static $other The time to compare with.
	 * @return bool
	 */
	public function isAfter( self $other ): bool {
		return $this->getTimestamp() > $other->getTimestamp();
	}

	/**
	 * Absolute difference in seconds between this time and another.
	 *
	 * @param static $other The time to diff against.
	 * @return int
	 */
	public function diffInSeconds( self $other ): int {
		return abs( $this->getTimestamp() - $other->getTimestamp() );
	}

	/* ─── Arithmetic ────────────────────────────────────────────────── */

	/**
	 * Return a new instance advanced by a relative date/time modifier.
	 *
	 * Examples: '+1 hour', '+7 days', 'next Monday'.
	 *
	 * @param string $modifier Relative date/time string.
	 * @return static
	 * @throws \RuntimeException If the modifier is invalid.
	 */
	public function advance( string $modifier ): static {
		$result = parent::modify( $modifier );
		if ( $result === false ) {
			throw new \RuntimeException(
				sprintf( 'Invalid date modifier: %s', $modifier )
			);
		}
		return static::wrap( $result );
	}

	/**
	 * Add an explicit number of seconds.
	 *
	 * @param int $seconds Seconds to add (may be negative).
	 * @return static
	 */
	public function addSeconds( int $seconds ): static {
		return static::fromTimestamp( $this->getTimestamp() + $seconds );
	}

	/* ─── Timezone conversion ───────────────────────────────────────── */

	/**
	 * Return a copy in the given timezone.
	 *
	 * @param DateTimeZone $tz Target timezone.
	 * @return static
	 */
	public function withTimezone( DateTimeZone $tz ): static {
		return static::wrap( parent::setTimezone( $tz ) );
	}

	/**
	 * Return a copy in the WordPress site timezone.
	 *
	 * @return static
	 */
	public function toSiteTimezone(): static {
		return $this->withTimezone( $this->site_timezone() );
	}

	/**
	 * Return a copy normalised to UTC (no-op when already UTC).
	 *
	 * @return static
	 */
	public function toUtc(): static {
		return $this->withTimezone( self::utc() );
	}

	/* ─── Formatting shortcuts used by templates ────────────────────── */

	/**
	 * Weekday name in the site timezone ('Monday', 'Tuesday', …).
	 *
	 * @return string
	 */
	public function dayOfWeek(): string {
		return $this->toSiteTimezone()->format( 'l' );
	}

	/**
	 * Year in the site timezone.
	 *
	 * @return string
	 */
	public function year(): string {
		return $this->toDisplay( 'Y' );
	}

	/**
	 * Month name in the site timezone.
	 *
	 * @return string
	 */
	public function month(): string {
		return $this->toDisplay( 'F' );
	}

	/**
	 * Day of the month in the site timezone.
	 *
	 * @return string
	 */
	public function day(): string {
		return $this->toDisplay( 'j' );
	}

	/* ─── Internal helpers ──────────────────────────────────────────── */

	/**
	 * Get the WordPress site timezone.
	 *
	 * @return DateTimeZone
	 */
	private function site_timezone(): DateTimeZone {
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}

		// Fallback: read the option manually.
		$tz_string = function_exists( 'get_option' )
			? get_option( 'timezone_string' )
			: '';

		if ( ! empty( $tz_string ) ) {
			return new DateTimeZone( $tz_string );
		}

		$offset = function_exists( 'get_option' )
			? (float) get_option( 'gmt_offset', 0 )
			: 0;

		// PHP accepts "+05:30" style timezone identifiers.
		$hours   = (int) $offset;
		$minutes = abs( (int) ( ( $offset - $hours ) * 60 ) );
		$sign    = $offset >= 0 ? '+' : '-';

		return new DateTimeZone(
			sprintf( '%s%02d:%02d', $sign, abs( $hours ), $minutes )
		);
	}

	/**
	 * Combined WP date + time format string.
	 *
	 * @return string
	 */
	private function wp_datetime_format(): string {
		$date_format = function_exists( 'get_option' )
			? get_option( 'date_format', 'Y-m-d' )
			: 'Y-m-d';
		$time_format = function_exists( 'get_option' )
			? get_option( 'time_format', 'H:i' )
			: 'H:i';

		return $date_format . ' ' . $time_format;
	}
}
