<?php

namespace MediaWiki\CheckUser;

use BadMethodCallException;
use InvalidArgumentException;
use MediaWiki\CommentStore\CommentStore;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Rdbms\Subquery;

/**
 * Allows the construction of SQL queries that allow CheckUser to read from the three
 *  tables that store events (cu_changes, cu_log_event and cu_private_event).
 *
 * @deprecated Alternative method followed, but keeping this in until the alternative method
 * is final. Once this happens this will be removed without notice.
 */
class CheckUserUnionSelectQueryBuilder extends SelectQueryBuilder {
	public const PRIVATE_LOG_EVENT_TABLE = 'cu_private_event';
	public const LOG_EVENT_TABLE = 'cu_log_event';
	public const CHANGES_TABLE = 'cu_changes';

	public const UNION_TABLES = [
		self::CHANGES_TABLE,
		self::LOG_EVENT_TABLE,
		self::PRIVATE_LOG_EVENT_TABLE
	];

	public const UNION_SELECT_ALIAS = 'CUSelectBuilderSubQuery';

	/** @var array<string,CheckUserUnionSubQueryBuilder> */
	protected $subQueriesForUnion;

	/** @var CommentStore */
	private CommentStore $commentStore;

	private bool $hasActorJoin = false;

	private bool $hasCommentJoin = false;

	/**
	 * @param IReadableDatabase $db
	 * @param CommentStore $commentStore
	 */
	public function __construct(
		IReadableDatabase $db,
		CommentStore $commentStore
	) {
		parent::__construct( $db );
		$this->commentStore = $commentStore;
		# Set up the sub queries to be UNIONed.
		# @phan-var array<string,CheckUserUnionSubQueryBuilder> $subQueriesForUnion
		$subQueriesForUnion = [];
		foreach ( self::UNION_TABLES as $table ) {
			$subQueriesForUnion[$table] = ( new CheckUserUnionSubQueryBuilder( $db ) )
				->table( $table );
		}
		# Always JOIN to the logging table for cu_log_event
		$subQueriesForUnion[self::LOG_EVENT_TABLE]->join(
			'logging', 'cu_log_event_logging', 'cu_log_event_logging.log_id = cule_log_id'
		);
		# Reset the last used alias to 'cu_log_event' for that subquery
		$subQueriesForUnion[self::LOG_EVENT_TABLE]->updateLastAlias( self::LOG_EVENT_TABLE );
		$this->subQueriesForUnion = $subQueriesForUnion;
	}

	/**
	 * Fill out table-specific arguments provided to one of the methods in this class,
	 *  so that the argument is table specific.
	 *
	 * If the provided argument is an array and does not have a key for one of the tables
	 *  in the SELECT query, the value is interpreted as applying to all tables.
	 *
	 * @param mixed $argument User provided argument
	 * @return array<string, mixed>
	 */
	private function fillTableSpecificArgument( $argument ): array {
		if ( is_array( $argument ) && array_intersect( array_keys( $argument ), self::UNION_TABLES ) !== [] ) {
			# If the array already has keys for the tables, then no pre-processing is needed.
			return $argument;
		}
		# If the array is not table specific, then return
		#  an array with the array as the value for all
		#  three tables.
		return [
			self::CHANGES_TABLE => $argument,
			self::PRIVATE_LOG_EVENT_TABLE => $argument,
			self::LOG_EVENT_TABLE => $argument
		];
	}

	/**
	 * Generates an empty array that can be used to specify different values
	 *  for each SELECT query in the UNION.
	 *
	 * @return array{cu_log_event:array,cu_changes:array,cu_private_event:array}
	 */
	public function getEmptyTableSpecificArgumentList(): array {
		return $this->fillTableSpecificArgument( [] );
	}

	/**
	 * Generates a list for use in arguments to ::subQuery* methods that allow the caller to
	 *  specify different values for each table in the UNION.
	 *
	 * @param mixed $cuChanges Value for the cu_changes table
	 * @param mixed $cuLogEvent Value for for the cu_log_event table
	 * @param mixed $cuPrivateLogEvent Value for the cu_private_event table
	 * @return array{cu_log_event:array,cu_changes:array,cu_private_event:array}
	 */
	public function generateTableSpecificArgumentList( $cuChanges, $cuLogEvent, $cuPrivateLogEvent ): array {
		$argumentList = $this->getEmptyTableSpecificArgumentList();
		$argumentList[self::CHANGES_TABLE] = $cuChanges;
		$argumentList[self::LOG_EVENT_TABLE] = $cuLogEvent;
		$argumentList[self::PRIVATE_LOG_EVENT_TABLE] = $cuPrivateLogEvent;
		return $argumentList;
	}

	/**
	 * No-op. This builder has a different structure (due to have UNIONed subqueries)
	 *  that make importing a query complicated. Will throw an BadMethodCallException
	 *  exception.
	 *
	 * @param array $info Ignored as this is a no-op.
	 * @throws BadMethodCallException
	 * @return never
	 */
	public function queryInfo( $info ) {
		throw new BadMethodCallException( 'CheckUserUnionSelectQueryBuilder::queryInfo() called which is a no-op' );
	}

	/**
	 * Sets the fields used in the SELECT query that wraps
	 *  the UNIONed SELECT queries.
	 *  * Fields specified here from the tables that are UNIONed
	 *     must be specified in the subqueries using ::subQueryFields
	 *  * If null is specified then all fields except cuc_private
	 *     and cule_private are selected.
	 *  * Fields specified here should not use any prefixes like
	 *     'cuc_' as these should be removed via aliases to reduce
	 *     the number of returned columns.
	 *
	 * If selecting any fields that come from the actor or comment
	 *  table, you must call ::needsActorJoin and/or ::needsCommentJoin
	 *
	 * Use ::subQueryFields() to set the fields used in each UNION subquery.
	 *
	 * @param string|string[]|null $fields
	 * @inheritDoc
	 */
	public function fields( $fields ) {
		if ( $fields === null ) {
			$subQueryFields = $this->getSubQueryFields( $this->fillTableSpecificArgument( $fields ) );
			$fields = array_keys( $subQueryFields[self::CHANGES_TABLE] );
		}
		return parent::fields( $fields );
	}

	/**
	 * Set the fields used in the SELECTs that are combined via a UNION.
	 *
	 * The caller can specify the fields for each table. To specify by table
	 *  provide use ::generateTableSpecificArgumentList and pass the result to this
	 *  method. To specify the same for all just pass an array of fields.
	 *
	 * The number of fields for each SELECT in the UNION must be the same. If
	 *  this is not the case in your provided fields an exception will be
	 *  raised.
	 *
	 * Fields specified here won't be returned in the results for the query unless
	 *  they are also specified through a call to ::fields.
	 *
	 * If selecting any fields that come from the actor or comment
	 *  table, you must call ::needsActorJoin and/or ::needsCommentJoin
	 *
	 *
	 * @param array|string|null $subQueryFields The field(s) to apply to the sub-queries
	 * @return $this
	 */
	public function subQueryFields( $subQueryFields ) {
		$subQueryFields = $this->fillTableSpecificArgument( $subQueryFields );
		$subQueryFields = $this->getSubQueryFields( $subQueryFields );
		if (
			count( $subQueryFields[self::CHANGES_TABLE] ) !== count( $subQueryFields[self::LOG_EVENT_TABLE] ) ||
			count( $subQueryFields[self::CHANGES_TABLE] ) !== count( $subQueryFields[self::PRIVATE_LOG_EVENT_TABLE] )
		) {
			throw new InvalidArgumentException(
				'Fields used in SELECTs that are UNION\'ed must have the same number of fields'
			);
		}
		foreach ( self::UNION_TABLES as $table ) {
			if ( array_key_exists( $table, $subQueryFields ) ) {
				$this->subQueriesForUnion[$table]->fields( $subQueryFields[$table] );
			}
		}
		return $this;
	}

	/**
	 * Adds where conditions to the queries that are UNIONed.
	 *
	 * The $conds parameter can be either the same for
	 *  all sub-queries or specific to each table.
	 *
	 * @param array|string $conds See ::where for more details
	 * @return $this
	 */
	public function subQueryWhere( $conds ) {
		$conds = $this->fillTableSpecificArgument( $conds );
		foreach ( self::UNION_TABLES as $table ) {
			if ( array_key_exists( $table, $conds ) ) {
				$this->subQueriesForUnion[$table]->where( $conds[$table] );
			}
		}
		return $this;
	}

	/**
	 * Set the USE INDEX option for each sub-query.
	 *
	 * The $index parameter is specific for each table.
	 *
	 * @param string[] $index See ::useIndex for more details
	 * @param bool $specifyTable If true, the index hint is applied to the specific
	 *  table used for results in the subquery instead of the last appended.
	 * @return $this
	 */
	public function subQueryUseIndex( $index, $specifyTable = true ) {
		foreach ( self::UNION_TABLES as $table ) {
			if ( array_key_exists( $table, $index ) ) {
				if ( $specifyTable ) {
					$this->subQueriesForUnion[$table]->useIndex( [ $table => $index[$table] ] );
				} else {
					$this->subQueriesForUnion[$table]->useIndex( $index[$table] );
				}
			}
		}
		return $this;
	}

	/**
	 * Set the ORDER BY option for sub queries. If already set, the
	 *  fields specified are appended.
	 *
	 * The $fields and $direction parameters can be either the same for
	 *  all sub-queries or specific to each table.
	 *
	 * @param string|string[] $fields See ::orderBy for more details
	 * @param string|string[]|null $direction See ::orderBy for more details
	 * @return $this
	 */
	public function subQueryOrderBy( $fields, $direction = null ) {
		$fields = $this->fillTableSpecificArgument( $fields );
		$direction = $this->fillTableSpecificArgument( $direction );
		foreach ( self::UNION_TABLES as $table ) {
			if ( array_key_exists( $table, $fields ) ) {
				$this->subQueriesForUnion[$table]->orderBy( $fields[$table], $direction[$table] ?? null );
			}
		}
		return $this;
	}

	/**
	 * Apply other options to the sub queries.
	 *
	 * The $options parameter can be either the same for
	 *  all sub-queries or specific to each table.
	 *
	 * @param array $options See ::options for more details
	 * @return $this
	 */
	public function subQueryOptions( array $options ) {
		$options = $this->fillTableSpecificArgument( $options );
		foreach ( self::UNION_TABLES as $table ) {
			if ( array_key_exists( $table, $options ) ) {
				$this->subQueriesForUnion[$table]->options( $options[$table] );
			}
		}
		return $this;
	}

	/**
	 * Sets the LIMIT value for all the subqueries.
	 *
	 * @param int $limit See ::limit for more details
	 * @return $this
	 */
	public function subQueryLimit( $limit ) {
		foreach ( self::UNION_TABLES as $table ) {
			$this->subQueriesForUnion[$table]->limit( $limit );
		}
		return $this;
	}

	/**
	 * Specifies that the query being run will need a JOIN to
	 * the actor table for each of the three tables results
	 * will be selected from.
	 *
	 * @return $this
	 */
	public function needsActorJoin() {
		if ( !$this->hasActorJoin ) {
			$joinForEachSubQuery = [
				self::CHANGES_TABLE => [
					'actor', 'cu_changes_actor', 'cu_changes_actor.actor_id = cuc_actor'
				],
				self::LOG_EVENT_TABLE => [
					'actor', 'cu_log_event_actor', 'cu_log_event_actor.actor_id = cule_actor'
				],
				self::PRIVATE_LOG_EVENT_TABLE => [
					'actor', 'cu_private_event_actor', 'cu_private_event_actor.actor_id = cupe_actor'
				]
			];
			foreach ( $joinForEachSubQuery as $table => $join ) {
				$currentLastAlias = $this->subQueriesForUnion[$table]->getLastAlias();
				$this->subQueriesForUnion[$table]->join( ...$join );
				# Reset the last alias to the value before this operation
				#  so this doesn't affect subQueryUseIndex
				$this->subQueriesForUnion[$table]->updateLastAlias( $currentLastAlias );
			}
			$this->hasActorJoin = true;
		}
		return $this;
	}

	/**
	 * Specifies that the query being run will need a JOIN to
	 * the comment table for each of the three tables results
	 * will be selected from.
	 *
	 * @return $this
	 */
	public function needsCommentJoin() {
		if ( !$this->hasCommentJoin ) {
			$joinToApplyToTables = [
				self::CHANGES_TABLE => $this->commentStore->getJoin( 'cuc_comment' ),
				self::LOG_EVENT_TABLE => $this->commentStore->getJoin( 'log_comment' ),
				self::PRIVATE_LOG_EVENT_TABLE => $this->commentStore->getJoin( 'cupe_comment' )
			];
			foreach ( $joinToApplyToTables as $table => $join ) {
				$currentLastAlias = $this->subQueriesForUnion[$table]->getLastAlias();
				$this->subQueriesForUnion[$table]->tables( $join['tables'] );
				$this->subQueriesForUnion[$table]->joinConds( $join['joins'] );
				# Reset the last alias to the value before this operation
				#  so this doesn't affect subQueryUseIndex
				$this->subQueriesForUnion[$table]->updateLastAlias( $currentLastAlias );
			}
			$this->hasCommentJoin = true;
		}
		return $this;
	}

	/**
	 * Adds the UNION sub-query to the overall wrapping
	 *  query using a call to ::table.
	 */
	private function setSubQueryBeforePerformingQuery() {
		if ( !$this->db->unionSupportsOrderAndLimit() ) {
			# If the DB does not support order and limit with a UNION,
			#  wrapping each SELECT that is UNIONed in another SELECT statement
			#  means that the LIMIT and ORDER BY are applied before the results
			#  are UNIONed. This only (currently) is needed for SQLite.
			$i = 0;
			$subQueriesAsSql = [];
			foreach ( $this->subQueriesForUnion as $subQuery ) {
				$subQueriesAsSql[] = $this->db->newSelectQueryBuilder()
					->field( '*' )
					->table( $subQuery, 'sqliteSubQuery' . $i )
					->getSQL();
				$i++;
			}
		} else {
			# Get the sub-queries as SQL
			$subQueriesAsSql = array_map( static function ( $selectQueryBuilder ) {
				return $selectQueryBuilder->getSQL();
			}, $this->subQueriesForUnion );
		}
		$this->table( new Subquery( $this->db->unionQueries(
			$subQueriesAsSql,
			$this->db::UNION_ALL
		) ), self::UNION_SELECT_ALIAS );
	}

	/**
	 * Removes the UNION sub-query from the top
	 *  SELECT query so that the user of this builder
	 *  can modify the sub-queries before performing
	 *  another query.
	 */
	private function resetSubQueryForFutureChanges() {
		unset( $this->tables[self::UNION_SELECT_ALIAS] );
	}

	/** @inheritDoc */
	public function fetchResultSet() {
		$this->setSubQueryBeforePerformingQuery();
		$result = parent::fetchResultSet();
		$this->resetSubQueryForFutureChanges();
		return $result;
	}

	/** @inheritDoc */
	public function fetchField() {
		$this->setSubQueryBeforePerformingQuery();
		$result = parent::fetchField();
		$this->resetSubQueryForFutureChanges();
		return $result;
	}

	/** @inheritDoc */
	public function fetchFieldValues() {
		$this->setSubQueryBeforePerformingQuery();
		$result = parent::fetchFieldValues();
		$this->resetSubQueryForFutureChanges();
		return $result;
	}

	/** @inheritDoc */
	public function fetchRow() {
		$this->setSubQueryBeforePerformingQuery();
		$result = parent::fetchRow();
		$this->resetSubQueryForFutureChanges();
		return $result;
	}

	/** @inheritDoc */
	public function fetchRowCount() {
		$this->setSubQueryBeforePerformingQuery();
		$result = parent::fetchRowCount();
		$this->resetSubQueryForFutureChanges();
		return $result;
	}

	/** @inheritDoc */
	public function estimateRowCount() {
		$this->setSubQueryBeforePerformingQuery();
		$result = parent::estimateRowCount();
		$this->resetSubQueryForFutureChanges();
		return $result;
	}

	/** @inheritDoc */
	public function getSQL() {
		$this->setSubQueryBeforePerformingQuery();
		$result = parent::getSQL();
		$this->resetSubQueryForFutureChanges();
		return $result;
	}

	/** @inheritDoc */
	public function getQueryInfo( $joinsName = 'join_conds' ) {
		$this->setSubQueryBeforePerformingQuery();
		$result = parent::getQueryInfo( $joinsName );
		$this->resetSubQueryForFutureChanges();
		return $result;
	}

	/**
	 * Gets the select fields for the sub-queries.
	 *
	 * The $fields must be table specific.
	 *
	 * @param array $fields
	 * @return string[][]
	 */
	private function getSubQueryFields( array $fields ): array {
		return [
			self::CHANGES_TABLE => $this->getSelectFieldsForTable( self::CHANGES_TABLE, $fields ),
			self::PRIVATE_LOG_EVENT_TABLE => $this->getSelectFieldsForTable(
				self::PRIVATE_LOG_EVENT_TABLE, $fields
			),
			self::LOG_EVENT_TABLE => $this->getSelectFieldsForTable(
				self::LOG_EVENT_TABLE, $fields
			),
		];
	}

	/**
	 * Gets the SELECT fields for the SELECT
	 *  in the UNION that uses the specified
	 *  table.
	 *
	 * @param string $table
	 * @param array $fields
	 * @return string[]
	 */
	private function getSelectFieldsForTable( string $table, array $fields ): array {
		$returnFields = [];

		$allSubQueryFieldsForTable = $this->getAllSelectFields( $table );

		if ( $fields[$table] === null ) {
			return $allSubQueryFieldsForTable;
		}

		if ( !is_array( $fields[$table] ) ) {
			$fieldsInternal = [ $fields[$table] ];
		} else {
			$fieldsInternal = $fields[$table] ?? [];
		}

		# Always include the timestamp in a sub query so that the
		#  wrapping query can order by said timestamp
		if ( !in_array( 'timestamp', $fieldsInternal ) ) {
			$fieldsInternal[] = 'timestamp';
		}

		foreach ( $fieldsInternal as $alias => $field ) {
			if ( is_int( $alias ) && array_key_exists( $field, $allSubQueryFieldsForTable ) ) {
				# First check if it's an alias defined in ::getAllSelectFields(),
				#  and if so then add the alias with the associated column name as defined by getAllSelectFields
				$returnFields += [ $field => $allSubQueryFieldsForTable[$field] ];
			} elseif ( is_int( $alias ) ) {
				# If the item in the array has a numeric key, add it which may possibly give it a new numeric key
				$returnFields[] = $field;
			} else {
				# Otherwise add the field unmodified to the return array
				$returnFields += [ $alias => $field ];
			}
		}

		return $returnFields;
	}

	/**
	 * Sets the value of every provided field to NULL.
	 *
	 * Used to meet the requirement that all the SELECT sub-queries have the
	 *  same number of columns. NULL will indicate that the value is not
	 *  applicable for this row.
	 *
	 * If using postgres the NULL will be cast to the type specified in the second
	 *  argument. This is because in postgres a NULL without a cast is assumed to
	 *  be of the text type which will not then UNION into the same column with integers.
	 *
	 * @param array $fields
	 * @param ?string $postgresType
	 * @return string[]
	 */
	private function markUnusedFieldsAsNull( array $fields, ?string $postgresType = null ): array {
		$fieldsToReturn = [];
		foreach ( $fields as $alias => $field ) {
			if ( is_numeric( $alias ) ) {
				$alias = $field;
			}
			if ( $this->db->getType() === 'postgres' && $postgresType !== null ) {
				# Needed because in postgres NULL needs to be cast to a type otherwise
				#  it assumes the type text which won't UNION to other types.
				$fieldsToReturn[$alias] = 'CAST(Null AS ' . $postgresType . ')';
			} else {
				$fieldsToReturn[$alias] = 'Null';
			}
		}
		return $fieldsToReturn;
	}

	/**
	 * Gets all possible fields that exist in the three
	 *  tables used for each SELECT sub-query.
	 *
	 * If a field does not correspond to a column in a particular
	 *  table it's value is set to NULL for all rows from that
	 *  table. This is needed because the SELECTs in a UNION must have
	 *  the same number of columns.
	 *
	 * The order of the columns must be kept the same when doing a
	 *  UNION query, so when updating this method be sure to keep the
	 *  columns in the same order for all three tables.
	 *
	 * @param string $table The table
	 * @return string[] The select fields
	 */
	private function getAllSelectFields( string $table ): array {
		# IMPORTANT: Keep the number of columns and order of these columns
		#  the same between all three tables. If not then the query will
		#  either fail or mix data incorrectly.
		if ( $table === self::CHANGES_TABLE ) {
			# Fields for cu_changes
			$fields = [
				'timestamp' => 'cuc_timestamp',
				'title' => 'cuc_title',
				'page_id' => 'cuc_page_id',
				'namespace' => 'cuc_namespace',
				'ip' => 'cuc_ip',
				'ip_hex' => 'cuc_ip_hex',
				'xff' => 'cuc_xff',
				'xff_hex' => 'cuc_xff_hex',
				'agent' => 'cuc_agent',
				'minor' => 'cuc_minor',
				'actiontext' => 'cuc_actiontext',
				'this_oldid' => 'cuc_this_oldid',
				'last_oldid' => 'cuc_last_oldid',
				'type' => 'cuc_type',
				'actor_user' => 'actor_user',
				'actor_name' => 'actor_name',
				'comment_text' => 'comment_text',
				'comment_data' => 'comment_data',
			];
			# These fields are in cu_private_event and cu_log_event,
			#  and must be Null here as they are not defined.
			$fields += $this->markUnusedFieldsAsNull( [
				'log_type',
				'log_action',
				'log_params',
			] );
			$fields += $this->markUnusedFieldsAsNull( [
				'log_id'
			], 'int' );
		} else {
			if ( $table === self::LOG_EVENT_TABLE ) {
				$fields = [
					'timestamp' => 'cule_timestamp',
					'title' => 'log_title',
					'page_id' => 'log_page',
					'namespace' => 'log_namespace',
					'ip' => 'cule_ip',
					'ip_hex' => 'cule_ip_hex',
					'xff' => 'cule_xff',
					'xff_hex' => 'cule_xff_hex',
					'agent' => 'cule_agent',
				];
			} else {
				$fields = [
					'timestamp' => 'cupe_timestamp',
					'title' => 'cupe_title',
					'page_id' => 'cupe_page',
					'namespace' => 'cupe_namespace',
					'ip' => 'cupe_ip',
					'ip_hex' => 'cupe_ip_hex',
					'xff' => 'cupe_xff',
					'xff_hex' => 'cupe_xff_hex',
					'agent' => 'cupe_agent',
				];
			}
			# Fields which are the same for queries on cu_private_event and cu_log_event
			$fields += $this->markUnusedFieldsAsNull( [
				'minor',
			], 'smallint' );
			$fields += $this->markUnusedFieldsAsNull( [
				'actiontext',
			] );
			$fields += $this->markUnusedFieldsAsNull( [
				'this_oldid',
				'last_oldid',
			], 'int' );
			if ( $this->db->getType() == 'postgres' ) {
				# On postgres the cuc_type type is a smallint.
				$fields += [
					'type' => 'CAST(' . RC_LOG . ' AS smallint)'
				];
			} else {
				# Other DBs can handle converting RC_LOG to the
				#  type of cuc_type.
				$fields += [
					'type' => RC_LOG
				];
			}
			$fields = array_merge( $fields, [
				'actor_user' => 'actor_user',
				'actor_name' => 'actor_name',
				'comment_text' => 'comment_text',
				'comment_data' => 'comment_data',
			] );
			if ( $table === self::LOG_EVENT_TABLE ) {
				$fields = array_merge( $fields, [
					'log_type' => 'log_type',
					'log_action' => 'log_action',
					'log_params' => 'log_params',
					'log_id' => 'cule_log_id'
				] );
			} else {
				$fields += [
					'log_type' => 'cupe_log_type',
					'log_action' => 'cupe_log_action',
					'log_params' => 'cupe_params'
				];
				$fields += $this->markUnusedFieldsAsNull( [ 'log_id' ], 'int' );
			}
		}
		return $fields;
	}

	/**
	 * @param string $table One of the strings in ::UNION_TABLES
	 * @return string
	 */
	public static function getPrefixForTable( string $table ): string {
		switch ( $table ) {
			case self::CHANGES_TABLE:
				return 'cuc_';
			case self::LOG_EVENT_TABLE:
				return 'cule_';
			case self::PRIVATE_LOG_EVENT_TABLE:
				return 'cupe_';
			default:
				throw new InvalidArgumentException( "Unrecognised table {$table}" );
		}
	}
}
