<?php

/**
 * Interbase/Firebird database server access classes
 *
 * @package objSQL
 * @version 3.9.0
 * @author MT Jordan <mtjo62@gmail.com>
 * @copyright 2016
 * @license zlib/libpng
 * @link http://objsql.sourceforge.net
 */


/*************************************************************************************************************
 * Begin database connection/utility class
 ************************************************************************************************************/


class obj_connection extends obj_access
{
    /**********************************************
     * Internal variables
     *********************************************/

    /**
     * Database connection object
     *
     * @access private
     * @var    mixed
     */
    private $obj_connection = false;

    /**
     * Database connection information
     *
     * @access private
     * @var array
     */
    private $obj_datasource;

    /**********************************************
     * Class methods
     *********************************************/

    /**
     * Constructor
     *
     * @access public
     * @param  array $datasource
     */
    public function __construct( $datasource )
    {
        $this->obj_datasource = $datasource;
    }

    /**
     * Returns database connection object
     *
     * @access protected
     * @return mixed
     */
    protected function obj_db_connection()
    {
		$obj_connection = ibase_connect( "{$this->obj_datasource[1]}:{$this->obj_datasource[4]}", $this->obj_datasource[2], $this->obj_datasource[3] );

		return ( is_resource( $obj_connection ) ) ? $this->obj_connection = $obj_connection : false;
    }

    /**
     * Closes connection to database server
     *
     * @access protected
     * @return bool
     */
    protected function obj_db_close()
    {
        return ibase_close( $this->obj_connection );
    }

    /**
     * Returns error flag for current connection instance
     *
     * @access protected
     * @return bool
     */
    protected function obj_db_error()
    {
        return ( ibase_errcode() != false ) ? true : false;
    }

    /**
     * Escapes string data for database insertion
     *
     * @access protected
     * @param  str $data
     * @return str
     */
    protected function obj_db_escape_data( $data )
    {
        return str_replace( "'", "''", $data );
    }

    /**
     * Returns error message for current connection instance
     *
     * @access protected
     * @return mixed
     */
    protected function obj_db_message()
    {
        return ( $this->obj_db_error() ) ? ibase_errmsg() : null;
    }

    /**
     * Returns database server information
     *
     * @access protected
     * @return array
     */
    protected function obj_db_info()
	{
		$obj_service = ibase_service_attach( $this->obj_datasource[1], $this->obj_datasource[2], $this->obj_datasource[3] );
		$obj_server_ver = ibase_server_info( $obj_service, IBASE_SVC_SERVER_VERSION );
		ibase_service_detach( $obj_service );

        return [$obj_server_ver, 'N/A', $this->obj_datasource[4]];
    }

    /**
     * Returns select paging query SQL statement
     *
     * @access protected
     * @param  str $table
     * @param  str $cols
     * @param  str $where
     * @param  str $order_by
     * @param  int $limit
     * @param  int $offset
     * @return str
     */
    protected function obj_db_paging( $table, $cols=false, $where=false, $order_by=false, $limit=1, $offset=1 )
    {
        return "SELECT FIRST $limit SKIP $offset $cols FROM $table $where $order_by";
    }

	/**
     * Returns row count for named table with arguments
     * Returns -1 if undetermined or failure
	 *
     * @access protected
	 * @param  str $table
     * @param  str $cols
     * @param  str $where
     * @return int
     */
    protected function obj_db_rowcount( $table, $cols=false, $where=false )
    {
        //default return value
        $obj_return = -1;

		$query_stmt = ibase_query( $this->obj_connection, "SELECT COUNT($cols) FROM $table $where" );

		if ( $query_stmt || is_resource( $query_stmt ) )
        {
		    $num_rows = ibase_fetch_row( $query_stmt );
            $obj_return = ( $num_rows[0] >= 0 ) ? $num_rows[0] : -1;

            ibase_free_result( $query_stmt );
        }

		return $obj_return;
    }
}


/*************************************************************************************************************
 * Begin database statement class
 ************************************************************************************************************/


class obj_statement
{
    /**********************************************
     * Internal variables
     *********************************************/

    /**
     * Database connection object
     *
     * @access private
     * @var    mixed
     */
    private $obj_connection;

    /**
     * Query string
     *
     * @access private
     * @var    str
     */
    private $obj_query;

	/**
     * Transaction resource
     *
     * @access private
     * @var    mixed
     */
	private $obj_trans_object;

    /**********************************************
     * Class methods
     *********************************************/

    /**
     * Constructor
     *
     * @access public
     * @param  str   $query
     * @param  mixed $connection
     * @param  mixed $transaction
     */
    function __construct( $query, $connection, $transaction )
    {
        $this->obj_connection = $connection->obj_connection;
		$this->obj_query = $query;
		$this->obj_trans_object = $transaction;
    }

    /**
     * Executes general query and returns resultset object
     *
     * @access public
     * @return mixed
     */
    public function obj_query_execute()
    {
		//if transaction is initiated, use transaction resource
		if ( is_resource( $this->obj_trans_object ) || is_object( $this->obj_trans_object ) )
			$query_stmt = ibase_query( $this->obj_trans_object, $this->obj_query );
		else
			$query_stmt = ibase_query( $this->obj_connection, $this->obj_query );

        return ( $query_stmt || is_resource( $query_stmt ) ) ? new obj_resultset( $query_stmt, $this->obj_connection ) : false;
	}
 }


/*************************************************************************************************************
 * Begin database prepared statement class
 ************************************************************************************************************/


class obj_prepare
{
    /**********************************************
     * Internal variables
     *********************************************/

    /**
     * Database connection object
     *
     * @access private
     * @var    mixed
     */
    private $obj_connection;

    /**
     * Prepared statement parameter values
     *
     * @access private
     * @var    array
     */
    private $obj_parameter = [];

    /**
     * Prepared statement instance
     *
     * @access public
     * @var    bool
     */
    private $obj_prepare_instance;

	/**
     * Statement resource
     *
     * @access private
     * @var    mixed
     */
	private $obj_query_stmt;

    /**
     * Transaction resource
     *
     * @access private
     * @var    mixed
     */
	private $obj_trans_object;

    /**********************************************
     * Class methods
     *********************************************/

    /**
     * Constructor
     *
     * @access public
     * @param  str   $query
     * @param  mixed $connection
	 * @param  mixed $param_vars
	 * @param  mixed $transaction
     */
    function __construct( $query, $connection, $param_vars, $transaction )
    {
		$param_vars = null;
        $this->obj_connection = $connection->obj_connection;
        $this->obj_prepare_init( $query, $transaction );
	}

    /**
     * Sets parameters and parameter types for prepared statement
     *
     * @access public
     * @param  mixed $param
     */
    public function obj_bind( $param )
    {
        $this->obj_parameter[] = $param;
    }

    /**
     * Destroys prepared statement object
     *
     * @access public
     * @return bool
     */
    public function obj_close_statement()
    {
        return ibase_free_query( $this->obj_prepare_instance );
    }

    /**
     * Binds parameters, executes prepared statement and returns resultset object
     *
     * @access public
     * @return mixed
     */
    public function obj_execute()
    {
		array_unshift( $this->obj_parameter, $this->obj_prepare_instance );

		$this->obj_query_stmt = call_user_func_array( 'ibase_execute', $this->obj_parameter );

		return ( $this->obj_query_stmt >= 0 || is_resource( $this->obj_query_stmt ) ) ? new obj_resultset( $this->obj_query_stmt, $this->obj_connection ) : false;
	}

    /**
     * Frees resultset memory from prepared statement object and resets binding parameters
     *
     * @access public
     * @return bool
     */
    public function obj_free_statement()
    {
        //default return value
        $obj_return = false;
        $this->obj_parameter = [];

		if ( is_resource( $this->obj_query_stmt ) )
			$obj_return = ibase_free_result( $this->obj_query_stmt );
		elseif ( is_integer( $this->obj_query_stmt ) || $this->obj_query_stmt === true )
			$obj_return = true;

		return $obj_return;
    }

    /**
     * Returns prepared statement instance
     *
     * @access private
     * @param  str $query
     * @return bool
     */
    private function obj_prepare_init( $query, $transaction )
    {
        //default return value
        $obj_return = false;

        if ( is_object( $transaction ) || is_resource( $transaction ) )
			$obj_return = $this->obj_prepare_instance = ibase_prepare( $this->obj_connection, $transaction, $query );
		else
			$obj_return = $this->obj_prepare_instance = ibase_prepare( $this->obj_connection, $query );

        return $obj_return;
    }
}


/*************************************************************************************************************
 * Begin database resultset class
 ************************************************************************************************************/


class obj_resultset
{
    /**********************************************
     * Internal variables
     *********************************************/

    /**
     * Database connection object
     *
     * @access private
     * @var    mixed
     */
    private $obj_connection;

    /**
     * Query record
     *
     * @access private
     * @var    array
     */
    private $obj_record = [];

    /**
     * Query resultset object
     *
     * @access private
     * @var    mixed
     */
    private $obj_result = false;

    /**********************************************
     * Class methods
     *********************************************/
    /**
     * Constructor
     *
     * @access public
     * @param  mixed $result
     * @param  mixed $connection
     */
    public function __construct( $result, $connection )
    {
        $this->obj_connection = $connection;
        $this->obj_result = $result;
    }

    /**
     * Returns number of affected rows from insert/delete/update query
     * Returns -1 if undetermined or failure
     *
     * @access public
     * @return int
     */
    public function obj_affected_rows()
    {
		$affected_rows = ( is_resource( $this->obj_result ) ) ? ibase_affected_rows( $this->obj_connection ) : $this->obj_result;

        return ( $affected_rows >= 0 ) ? $affected_rows : -1;
    }

    /**
     * Returns resultset object as associative array
     *
     * @access public
     * @return mixed
     */
    public function obj_fetch_assoc()
    {
        $result = ibase_fetch_assoc( $this->obj_result );

        return ( is_array( $result ) && $result != false ) ? $this->obj_record = $result : null;
    }

    /**
     * Returns resultset object as numeric array
     *
     * @access public
     * @return mixed
     */
    public function obj_fetch_num()
    {
        $result = ibase_fetch_row( $this->obj_result );

        return ( is_array( $result ) && $result != false ) ? $this->obj_record = $result : null;
    }

    /**
     * Returns resultset object as object
     *
     * @access public
     * @return mixed
     */

    public function obj_fetch_object()
    {
        $result = ibase_fetch_object( $this->obj_result );

        return ( is_object( $result ) && $result != false ) ? $this->obj_record = $result : null;
    }

    /**
     * Returns resultset record
     *
     * @access public
     * @param  mixed $field
     * @return mixed
     */
    public function obj_field( $field )
    {
        if ( $this->obj_result )
        {
            //get_magic_quotes deprecated in php 5.4 - added for backwards compatibility
            return ( get_magic_quotes_runtime() ) ? stripslashes( $this->obj_record[strtoupper( $field )] ) : $this->obj_record[strtoupper( $field )];
        }
    }

    /**
     * Frees resultset memory and destroys resultset object
     *
     * @access public
     * @return bool
     */
    public function obj_free_result()
    {
        $this->obj_record = [];

        return ibase_free_result( $this->obj_result );
    }

    /**
     * Returns number of fields from query
     * Returns -1 if undetermined or failure
     *
     * @access public
     * @return int
     */
    public function obj_num_fields()
    {
        $num_cols = ibase_num_fields( $this->obj_result );

        return ( $num_cols >= 0 ) ? $num_cols : -1;
    }

    /**
     * Returns number rows from query
     * Returns -1 if undetermined or failure
     *
     * @access public
     * @return int
     */
    public function obj_num_rows()
    {
        //Very inefficent - will timeout on larger recordsets
		//Use the obj_row_count() method
		$num_rows = 0;

        while ( ibase_fetch_row( $this->obj_result ) )
			$num_rows++;

        return ( $num_rows >= 0 ) ? $num_rows : -1;
    }
}


/*************************************************************************************************************
 * Begin database transaction class
 ************************************************************************************************************/


class obj_transaction
{
	/**********************************************
     * Internal variables
     *********************************************/

	/**
     * Database connection instance
     *
     * @access private
     * @var    mixed
     */
    private $obj_connection;

	/**
     * Transaction resource
     *
     * @access private
     * @var    mixed
     */
	public $obj_trans_object;

    /**********************************************
     * Class methods
     *********************************************/

	/**
     * Constructor
     *
     * @access public
     * @param  mixed $connection
     */
    public function __construct( $connection )
    {
        $this->obj_connection = $connection;

		//turn off autocommit
		$this->obj_trans_object = ibase_trans( $this->obj_connection );
	}

    /**
     * Commits transaction for current transaction instance
     *
     * @access public
     * @return bool
     */
    public function obj_commit()
    {
	    return ( is_resource( $this->obj_trans_object ) || is_object( $this->obj_trans_object ) ) ? ibase_commit( $this->obj_trans_object ) : false;
    }

    /**
     * Rollbacks transaction for current transaction instance
     *
     * @access public
     * @param  str $savepoint
     * @return bool
     */
    public function obj_rollback( $savepoint=false )
    {
        if ( !$savepoint )
			$rollback = ibase_rollback( $this->obj_trans_object );
        else
            $rollback = ibase_query( $this->obj_trans_object, "ROLLBACK TO SAVEPOINT $savepoint" );

        return ( $rollback || is_resource( $rollback ) || is_object( $rollback ) ) ? true : false;
    }

    /**
     * Creates transaction savepoint for current transaction instance
     *
     * @access public
     * @param  str $savepoint
     * @return bool
     */
    public function obj_savepoint( $savepoint )
    {
        return ( ibase_query( $this->obj_trans_object, "SAVEPOINT $savepoint" ) ) ? true : false;
    }
}


/* EOF driver.php */
/* Location: ./drivers/firebird/driver.php */