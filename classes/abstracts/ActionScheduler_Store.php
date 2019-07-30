<?php

/**
 * Class ActionScheduler_Store
 * @codeCoverageIgnore
 */
abstract class ActionScheduler_Store extends ActionScheduler_Store_Deprecated {
	const STATUS_COMPLETE = 'complete';
	const STATUS_PENDING  = 'pending';
	const STATUS_RUNNING  = 'in-progress';
	const STATUS_FAILED   = 'failed';
	const STATUS_CANCELED = 'canceled';
	const DEFAULT_CLASS   = 'ActionScheduler_wpPostStore';

	/** @var ActionScheduler_Store */
	private static $store = NULL;

	/**
	 * @param ActionScheduler_Action $action
	 * @param DateTime $scheduled_date Optional Date of the first instance
	 *        to store. Otherwise uses the first date of the action's
	 *        schedule.
	 *
	 * @return string The action ID
	 */
	abstract public function save_action( ActionScheduler_Action $action, DateTime $scheduled_date = NULL );

	/**
	 * @param string $action_id
	 *
	 * @return ActionScheduler_Action
	 */
	abstract public function fetch_action( $action_id );

	/**
	 * @param string $hook
	 * @param array $params
	 * @return string ID of the next action matching the criteria
	 */
	abstract public function find_action( $hook, $params = array() );

	/**
	 * @param array $query
	 * @return array The IDs of actions matching the query
	 */
	abstract public function query_actions( $query = array() );

	/**
	 * Get a count of all actions in the store, grouped by status
	 *
	 * @return array
	 */
	abstract public function action_counts();

	/**
	 * @param string $action_id
	 */
	abstract public function cancel_action( $action_id );

	/**
	 * @param string $action_id
	 */
	abstract public function delete_action( $action_id );

	/**
	 * @param string $action_id
	 *
	 * @return DateTime The date the action is schedule to run, or the date that it ran.
	 */
	abstract public function get_date( $action_id );


	/**
	 * @param int      $max_actions
	 * @param DateTime $before_date Claim only actions schedule before the given date. Defaults to now.
	 * @param array    $hooks       Claim only actions with a hook or hooks.
	 * @param string   $group       Claim only actions in the given group.
	 *
	 * @return ActionScheduler_ActionClaim
	 */
	abstract public function stake_claim( $max_actions = 10, DateTime $before_date = null, $hooks = array(), $group = '' );

	/**
	 * @return int
	 */
	abstract public function get_claim_count();

	/**
	 * @param ActionScheduler_ActionClaim $claim
	 */
	abstract public function release_claim( ActionScheduler_ActionClaim $claim );

	/**
	 * @param string $action_id
	 */
	abstract public function unclaim_action( $action_id );

	/**
	 * @param string $action_id
	 */
	abstract public function mark_failure( $action_id );

	/**
	 * @param string $action_id
	 */
	abstract public function log_execution( $action_id );

	/**
	 * @param string $action_id
	 */
	abstract public function mark_complete( $action_id );

	/**
	 * @param string $action_id
	 *
	 * @return string
	 */
	abstract public function get_status( $action_id );

	/**
	 * @param string $action_id
	 * @return mixed
	 */
	abstract public function get_claim_id( $action_id );

	/**
	 * @param string $claim_id
	 * @return array
	 */
	abstract public function find_actions_by_claim_id( $claim_id );

	/**
	 * @param string $comparison_operator
	 * @return string
	 */
	protected function validate_sql_comparator( $comparison_operator ) {
		if ( in_array( $comparison_operator, array('!=', '>', '>=', '<', '<=', '=') ) ) {
			return $comparison_operator;
		}
		return '=';
	}

	/**
	 * Get the time MySQL formated date/time string for an action's (next) scheduled date.
	 *
	 * @param ActionScheduler_Action $action
	 * @param DateTime $scheduled_date (optional)
	 * @return string
	 */
	protected function get_scheduled_date_string( ActionScheduler_Action $action, DateTime $scheduled_date = NULL ) {
		$next = null === $scheduled_date ? $action->get_schedule()->next() : $scheduled_date;
		if ( ! $next ) {
			return '0000-00-00 00:00:00';
		}
		$next->setTimezone( new DateTimeZone( 'UTC' ) );

		return $next->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Get the time MySQL formated date/time string for an action's (next) scheduled date.
	 *
	 * @param ActionScheduler_Action $action
	 * @param DateTime $scheduled_date (optional)
	 * @return string
	 */
	protected function get_scheduled_date_string_local( ActionScheduler_Action $action, DateTime $scheduled_date = NULL ) {
		$next = null === $scheduled_date ? $action->get_schedule()->next() : $scheduled_date;
		if ( ! $next ) {
			return '0000-00-00 00:00:00';
		}

		ActionScheduler_TimezoneHelper::set_local_timezone( $next );
		return $next->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Validate that we could decode action arguments.
	 *
	 * @param mixed $args      The decoded arguments.
	 * @param int   $action_id The action ID.
	 *
	 * @throws ActionScheduler_InvalidActionException When the decoded arguments are invalid.
	 */
	protected function validate_args( $args, $action_id ) {
		// Ensure we have an array of args.
		if ( ! is_array( $args ) ) {
			throw ActionScheduler_InvalidActionException::from_decoding_args( $action_id );
		}

		// Validate JSON decoding if possible.
		if ( function_exists( 'json_last_error' ) && JSON_ERROR_NONE !== json_last_error() ) {
			throw ActionScheduler_InvalidActionException::from_decoding_args( $action_id, $args );
		}
	}

	/**
	 * Validate a ActionScheduler_Schedule object.
	 *
	 * @param mixed $schedule  The unserialized ActionScheduler_Schedule object.
	 * @param int   $action_id The action ID.
	 *
	 * @throws ActionScheduler_InvalidActionException When the schedule is invalid.
	 */
	protected function validate_schedule( $schedule, $action_id ) {
		if ( empty( $schedule ) || ! is_a( $schedule, 'ActionScheduler_Schedule' ) ) {
			throw ActionScheduler_InvalidActionException::from_schedule( $post->ID, $schedule );
		}
	}

	/**
	 * @return array
	 */
	public function get_status_labels() {
		return array(
			self::STATUS_COMPLETE => __( 'Complete', 'action-scheduler' ),
			self::STATUS_PENDING  => __( 'Pending', 'action-scheduler' ),
			self::STATUS_RUNNING  => __( 'In-progress', 'action-scheduler' ),
			self::STATUS_FAILED   => __( 'Failed', 'action-scheduler' ),
			self::STATUS_CANCELED => __( 'Canceled', 'action-scheduler' ),
		);
	}

	/**
	 * Check if there are any pending scheduled actions due to run.
	 *
	 * @param ActionScheduler_Action $action
	 * @param DateTime $scheduled_date (optional)
	 * @return string
	 */
	public function has_pending_actions_due() {
		$pending_actions = $this->query_actions( array(
			'date'   => as_get_datetime_object(),
			'status' => ActionScheduler_Store::STATUS_PENDING,
		) );

		return ! empty( $pending_actions );
	}

	public function init() {}

	/**
	 * @return ActionScheduler_Store
	 */
	public static function instance() {
		if ( empty( self::$store ) ) {
			$class = apply_filters( 'action_scheduler_store_class', self::DEFAULT_CLASS );
			self::$store = new $class();
		}
		return self::$store;
	}
}