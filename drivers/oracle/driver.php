<?php

/**
 * Oracle database server access classes
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
     * @var    array
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
        $obj_port = ( array_key_exists( 5, $this->obj_datasource ) ) ? (int)$this->obj_datasource[5] : false;

        if ( $obj_port )
        {
            $obj_connection = oci_connect( $this->obj_datasource[2], $this->obj_datasource[3], "//{$this->obj_datasource[1]}:$obj_port/{$this->obj_datasource[4]}" );
        }
        else
        {
            $obj_connection = oci_connect( $this->obj_datasource[2], $this->obj_datasource[3], "//{$this->obj_datasource[1]}/{$this->obj_datasource[4]}" );
        }

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
        return oci_close( $this->obj_connection );
    }

    /**
     * Returns error flag for current connection instance
     *
     * @access protected
     * @return bool
     */
    protected function obj_db_error()
    {
        return ( oci_error() ) ? true : false;
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
        return ( $this->obj_db_error() ) ? oci_error()['message'] : null;
    }

    /**
     * Returns database server information
     *
     * @access protected
     * @return array
     */
    protected function obj_db_info()
    {
        $query_stmt = oci_parse( $this->obj_connection, "SELECT USERENV ('language') FROM DUAL" );
        oci_execute( $query_stmt );

        $obj_encoding = oci_fetch_row( $query_stmt )[0];
        oci_free_statement( $query_stmt );

        return [oci_server_version( $this->obj_connection ), $obj_encoding, $this->obj_datasource[4]];
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
        $limit_str = ( $offset > 0 ) ? ( $offset + $limit ) - 1 : $offset + $limit;

        return "SELECT * FROM ( SELECT rownum rnum, a.*
                FROM (
                SELECT $cols
                FROM $table
                $where $order_by ) a
                WHERE rownum <= $limit_str )
                WHERE rnum >= $offset";
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

		$query_stmt = oci_parse( $this->obj_connection, "SELECT COUNT($cols) FROM $table $where" );
		$exec_query_stmt = oci_execute( $query_stmt );

		if ( is_resource( $query_stmt ) || $exec_query_stmt )
		{
            $num_rows = oci_fetch_row( $query_stmt );
            $obj_return = ( $num_rows[0] >= 0 ) ? $num_rows[0] : -1;

            oci_free_statement( $query_stmt );
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
     * Transaction flag
     *
     * @access private
     * @var    bool
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
		$query_stmt = oci_parse( $this->obj_connection, $this->obj_query );

		if ( $this->obj_trans_object )
        {
			$exec_query_stmt = oci_execute( $query_stmt, OCI_NO_AUTO_COMMIT );
        }
        else
        {
			$exec_query_stmt = oci_execute( $query_stmt );
        }

        return ( is_resource( $query_stmt ) && $exec_query_stmt ) ? new obj_resultset( $query_stmt, $this->obj_connection ) : false;
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
     * Set obj_bind parameter counter
     *
     * @access private
     * @var    int
     */
	private $obj_parameter_cnt = 1;

    /**
     * Prepared statement instance
     *
     * @access public
     * @var    bool
     */
    private $obj_prepare_instance;

   /**
     * Transaction flag
     *
     * @access private
     * @var    bool
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
        $this->obj_connection = $connection->obj_connection;
		$this->obj_trans_object = $transaction;
        $this->obj_prepare_init( $query );
    }

    /**
     * Sets parameters and parameter types for prepared statement
     *
     * @access public
     * @param  mixed $param
     */
    public function obj_bind( $param )
    {
        $param_name = $this->obj_parameter_cnt++;

		oci_bind_by_name( $this->obj_prepare_instance, ":$param_name", $param );
	}

    /**
     * Destroys prepared statement object
     *
     * @access public
     * @return bool
     */
    public function obj_close_statement()
    {
        return oci_free_statement( $this->obj_prepare_instance );
    }

    /**
     * Binds parameters, executes prepared statement and returns resultset object
     *
     * @access public
     * @return mixed
     */
    public function obj_execute()
    {
        if ( $this->obj_trans_object )
        {
			$exec_query_stmt = oci_execute( $this->obj_prepare_instance, OCI_NO_AUTO_COMMIT );
        }
        else
        {
			$exec_query_stmt = oci_execute( $this->obj_prepare_instance );
        }

        return ( $exec_query_stmt ) ? new obj_resultset( $this->obj_prepare_instance, $this->obj_connection ) : false;
    }

    /**
     * Frees resultset memory from prepared statement object and resets binding parameters
     *
     * @access public
     * @return bool
     */
    public function obj_free_statement()
    {
        $this->obj_parameter_cnt = 1;

        return true;
    }

    /**
     * Returns prepared statement instance
     *
     * @access private
     * @param  str $query
     * @return bool
     */
    private function obj_prepare_init( $query )
    {
        return $this->obj_prepare_instance = oci_parse( $this->obj_connection, $this->obj_prepare_sql( $query ) );
    }

	/**
     * Replaces (?) markers in prepared statement query string with placeholder (:1,:2,etc)
     *
     * @access private
     * @return str
     */
    private function obj_prepare_sql( $query )
    {
        $query_parts = explode( '?', $query );
        $query_sql = $query_parts[0];

        for ( $i = 1; $i < count( $query_parts ); $i++ )
        {
            $query_sql .= ":$i {$query_parts[$i]}";
        }

        return $query_sql;
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
        $affected_rows = oci_num_rows( $this->obj_result );

        return ( $affected_rows !== false && $affected_rows >= 0 ) ? $affected_rows : -1;
    }

    /**
     * Returns resultset object as associative array
     *
     * @access public
     * @return mixed
     */
    public function obj_fetch_assoc()
    {
        $result = oci_fetch_assoc( $this->obj_result );

        return ( is_array( $result ) && $result !== false ) ? $this->obj_record = $result : null;
    }

    /**
     * Returns resultset object as numeric array
     *
     * @access public
     * @return mixed
     */
    public function obj_fetch_num()
    {
        $result = oci_fetch_row( $this->obj_result );

        return ( is_array( $result ) && $result !== false ) ? $this->obj_record = $result : null;
    }

    /**
     * Returns resultset object as object
     *
     * @access public
     * @return mixed
     */

    public function obj_fetch_object()
    {
        $result = oci_fetch_object( $this->obj_result );

        return ( is_object( $result ) && $result !== false ) ? $this->obj_record = $result : null;
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
        oci_free_statement( $this->obj_result );
        $this->obj_record = [];

        return ( !is_resource( $this->obj_result = null ) ) ? true : false;
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
        $num_cols = oci_num_fields( $this->obj_result );

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
        while ( oci_fetch_row( $this->obj_result ) ) {}
		$num_rows = oci_num_rows( $this->obj_result );

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
    public $obj_connection;

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
	}

    /**
     * Commits transaction for current transaction instance
     *
     * @access public
     * @return bool
     */
    public function obj_commit()
    {
        return oci_commit( $this->obj_connection );
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
        //default return value
        $obj_return = false;

        if ( !$savepoint )
        {
            $obj_return = oci_rollback( $this->obj_connection );
        }
        else
		{
			$rollback = oci_parse( $this->obj_connection, "ROLLBACK TO SAVEPOINT $savepoint" );
			$exec_rollback = oci_execute( $rollback, OCI_NO_AUTO_COMMIT );

            $obj_return = ( is_resource( $rollback ) && $exec_rollback ) ? true : false;
		}

        return $obj_return;
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
		$obj_savepoint = oci_parse( $this->obj_connection, "SAVEPOINT $savepoint" );
		$exec_savepoint = oci_execute( $obj_savepoint, OCI_NO_AUTO_COMMIT );

        return ( is_resource( $obj_savepoint ) && $exec_savepoint ) ? true : false;
    }
}

/* EOF driver.php */
/* Location: ./drivers/oracle/driver.php */