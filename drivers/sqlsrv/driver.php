<?php

/**
 * SQL Server database access classes
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
    private $obj_connection;

    /**
     * Database connection information
     *
     * @access private
     * @var    array
     */
    private $obj_datasource;

    /**********************************************
     * Class methods
     *********************************************/

    /**
     * Constructor
     *
     * @param array $datasource
     */
    public function __construct( $datasource )
    {
        $this->obj_datasource = $datasource;
    }

    /**
     * Returns database connection instance
     *
     * @return mixed
     */
    protected function obj_db_connection()
    {
        $obj_port = ( array_key_exists( 5, $this->obj_datasource ) ) ? (int)$this->obj_datasource[5] : false;
        $db_host = ( $obj_port ) ? "{$this->obj_datasource[1]},$obj_port" : $this->obj_datasource[1];

        $obj_connection = sqlsrv_connect( $db_host, array( 'Database' => $this->obj_datasource[4],
                                                           'UID'      => $this->obj_datasource[2],
                                                           'PWD'      => $this->obj_datasource[3] ) );

        return is_resource( $obj_connection ) ? $this->obj_connection = $obj_connection : false;
    }

    /**
     * Closes connection to database server
     *
     * @access protected
     * @return bool
     */
    protected function obj_db_close()
    {
        return sqlsrv_close( $this->obj_connection );
    }

    /**
     * Returns error flag for current connection instance
     *
     * @access protected
     * @return bool
     */
    protected function obj_db_error()
    {
        return ( sqlsrv_errors() !== null ) ? true : false;
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
     * @return str
     */
    protected function obj_db_message()
    {
        $err_msg = sqlsrv_errors();

        return ( $this->obj_db_error() ) ? $err_msg[0]['message'] : null;
    }

    /**
     * Returns database server information
     *
     * @access protected
     * @return array
     */
    protected function obj_db_info()
    {
        $query_stmt = sqlsrv_query( $this->obj_connection, "select collation_name from sys.databases" );
        $charset = sqlsrv_fetch_object( $query_stmt );
        $version = sqlsrv_server_info( $this->obj_connection );

        return [$version['SQLServerVersion'], $charset->collation_name, $this->obj_datasource[4]];
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
        //default return value
        $obj_return = '';

        // check for SQL Server 2012+
		if ( explode( '.',  $this->obj_db_info()[0] ) < 11 )
        {
			$obj_return = "WITH temp_table AS (
                          SELECT ROW_NUMBER() OVER ( $order_by ) AS RowNumber, $cols
                          FROM $table $where )
                          SELECT *
                          FROM temp_table
                          WHERE RowNumber > $offset AND RowNumber <= ( $limit + $offset )";
        }
		else
        {
            // added to support SQL Server 2012 - throws cursor exception using row_number method
            $obj_return = "SELECT $cols FROM $table $where $order_by
                          OFFSET $offset ROWS
                          FETCH NEXT $limit ROWS ONLY";
        }

        return $obj_return;
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

		$query_stmt = sqlsrv_query( $this->obj_connection, "SELECT COUNT($cols) FROM $table $where", array(), array( 'Scrollable' => SQLSRV_CURSOR_STATIC ) );
        
        if ( is_resource( $query_stmt ) )
		{
            $num_rows = sqlsrv_fetch_array( $query_stmt, SQLSRV_FETCH_NUMERIC );
            $obj_return = ( $num_rows[0] >= 0 ) ? $num_rows[0] : -1;

            sqlsrv_free_stmt( $query_stmt );
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
     * @var    string
     */
    private $obj_query;

    /**********************************************
     * Class methods
     *********************************************/

    /**
     * Constructor
     *
     * @access public
     * @param  str   $query
     * @param  mixed $connection
     */
    function __construct( $query, $connection )
    {
        $this->obj_connection = $connection->obj_connection;
        $this->obj_query = $query;
    }

    /**
     * Executes general query and returns resultset resource
     *
     * @access public
     * @return mixed
     */
    public function obj_query_execute()
    {
        if ( ( stripos( $this->obj_query, 'select' ) === 0 ) || ( stripos( $this->obj_query, 'with' ) === 0 ) )
        {
            $query_stmt = sqlsrv_query( $this->obj_connection, $this->obj_query, array(), array( 'Scrollable' => SQLSRV_CURSOR_STATIC ) );
        }
        else
        {
            $query_stmt = sqlsrv_query( $this->obj_connection, $this->obj_query );
        }

        return ( is_resource( $query_stmt ) ) ? new obj_resultset( $query_stmt, $this->obj_connection ) : false;
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
     * Prepared query instance
     *
     * @access private
     * @var    mixed
     */
    private $obj_prepare_instance;

    /**********************************************
     * Class methods
     *********************************************/

    /**
     * Constructor
     *
     * @access public
	 * @param  str   $query
     * @param  mixed $connection
     * @param  array $param_vars
     */
    function __construct( $query, $connection, $param_vars )
    {
        $this->obj_connection = $connection->obj_connection;
		$this->obj_prepare_init( $query, $param_vars );
    }

    /**
     * SQL Server doesn't implement a binding method
     *
     * @access public
     */
    public function obj_bind() {}

    /**
     * Destroys prepared statement resource
     *
     * @access public
     * @return bool
     */
    public function obj_close_statement()
    {
        return sqlsrv_free_stmt( $this->obj_prepare_instance );
    }

    /**
     * Executes prepared statement and returns resultset
     *
     * @access public
     * @return mixed
     */
    public function obj_execute()
    {
        $query_stmt = sqlsrv_execute( $this->obj_prepare_instance );

        return ( $query_stmt ) ? new obj_resultset( $this->obj_prepare_instance, $this->obj_connection ) : false;
    }

    /**
     * Frees resultset memory from prepared statement resource
     *
     * @access public
     * @return bool
     */
    public function obj_free_statement()
    {
        return sqlsrv_cancel( $this->obj_prepare_instance );
    }

    /**
     * Returns prepared statement resource
     *
     * @access private
     * @param  str   $query
     * @param  array $param_vars
     * @return mixed
     */
    private function obj_prepare_init( $query, $param_vars )
    {
        if ( ( stripos( $query, 'select' ) === 0 ) || ( stripos( $query, 'with' ) === 0 ) )
        {
            $prepare_instance = sqlsrv_prepare( $this->obj_connection, $query, $param_vars, array( 'Scrollable' => SQLSRV_CURSOR_STATIC ) );
        }
        else
        {
            $prepare_instance = sqlsrv_prepare( $this->obj_connection, $query, $param_vars );
        }

        return ( is_resource( $prepare_instance ) ) ? $this->obj_prepare_instance = $prepare_instance : false;
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
    private $obj_result;

    /**********************************************
     * Class methods
     *********************************************/

    /**
     * Constructor
     *
     * @access public
     * @param  mixed $result
     */
    public function __construct( $result )
    {
        $this->obj_result = $result;
    }

    /**
     * Return number of affected rows from insert/delete/update query
     * Returns -1 if undetermined or failure
     *
     * @access public
     * @return int
     */
    public function obj_affected_rows()
    {
        $affected_rows = sqlsrv_rows_affected( $this->obj_result );

        return ( $affected_rows !== false && $affected_rows >= 0 ) ? $affected_rows : -1;
    }

    /**
     * Returns resultset resource as associative array
     *
     * @access public
     * @return mixed
     */
    public function obj_fetch_assoc()
    {
        $result = sqlsrv_fetch_array( $this->obj_result, SQLSRV_FETCH_ASSOC );

        return ( is_array( $result ) ) ? $this->obj_record = $result : null;
    }

    /**
     * Returns resultset resource as numeric array
     *
     * @access public
     * @return mixed
     */
    public function obj_fetch_num()
    {
        $result = sqlsrv_fetch_array( $this->obj_result, SQLSRV_FETCH_NUMERIC );

        return ( is_array( $result ) ) ? $this->obj_record = $result : null;
    }

    /**
     * Returns resultset resource as object
     *
     * @access public
     * @return mixed
     */
    public function obj_fetch_object()
    {
        $result = sqlsrv_fetch_object( $this->obj_result );

        return ( is_object( $result ) ) ? $this->obj_record = $result : null;
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
        //get_magic_quotes deprecated in php 5.4 - added for backwards compatibility
        return ( get_magic_quotes_runtime() ) ? stripslashes( $this->obj_record[$field] ) : $this->obj_record[$field];
    }

    /**
     * Frees resultset memory and destroy resultset resource
     *
     * @access public
     * @return bool
     */
    public function obj_free_result()
    {
        $this->obj_record = [];

        return sqlsrv_free_stmt( $this->obj_result );
    }

    /**
     * Return number of fields from query
     * Returns -1 if undetermined or failure
     *
     * @access public
     * @return int
     */
    public function obj_num_fields()
    {
        $num_cols = sqlsrv_num_fields( $this->obj_result );

        return ( $num_cols !== false && $num_cols >= 0 ) ? $num_cols : -1;
    }

    /**
     * Returns number of rows from query
     * Returns -1 if undetermined or failure
     *
     * @access public
     * @return int
     */
    public function obj_num_rows()
    {
        $num_rows = sqlsrv_num_rows( $this->obj_result );

        return ( $num_rows !== false && $num_rows >= 0 ) ? $num_rows : -1;
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
        sqlsrv_begin_transaction( $this->obj_connection );
    }

    /**
     * Commits transaction for current transaction instance
     *
     * @access public
     * @return bool
     */
    public function obj_commit()
    {
        return sqlsrv_commit( $this->obj_connection );
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
        {
            $rollback = sqlsrv_rollback( $this->obj_connection );
        }
        else
        {
            $rollback = sqlsrv_query( $this->obj_connection, "ROLLBACK TRANSACTION $savepoint" );
        }

        return ( $rollback || is_resource( $rollback ) ) ? true : false;
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
        return ( is_resource( sqlsrv_query( $this->obj_connection, "SAVE TRANSACTION $savepoint" ) ) ) ? true : false;
    }
}


/* EOF driver.php */
/* Location: ./drivers/sqlsrv/driver.php */