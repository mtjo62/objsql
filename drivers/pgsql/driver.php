<?php

/**
 * PostgreSQL database server access classes
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
     * @access public
     * @param  array $datasource
     */
    public function __construct( $datasource )
    {
        $this->obj_datasource = $datasource;
    }

    /**
     * Returns database connection instance
     *
     * @access protected
     * @return mixed
     */
    protected function obj_db_connection()
    {
        $obj_port = ( array_key_exists( 5, $this->obj_datasource ) ) ? (int)$this->obj_datasource[5] : false;

        if ( $obj_port )
        {
            $obj_connection = pg_connect( "host={$this->obj_datasource[1]} port=$obj_port dbname={$this->obj_datasource[4]} user={$this->obj_datasource[2]} password={$this->obj_datasource[3]}" );
        }
        else
        {
            $obj_connection = pg_connect( "host={$this->obj_datasource[1]} dbname={$this->obj_datasource[4]} user={$this->obj_datasource[2]} password={$this->obj_datasource[3]}" );
        }

        return ( is_resource( $obj_connection ) ) ? $this->obj_connection = $obj_connection: false;
    }

    /**
     * Closes connection to database server
     *
     * @access protected
     * @return bool
     */
    protected function obj_db_close()
    {
        return pg_close( $this->obj_connection );
    }

    /**
     * Returns error flag for current connection instance
     *
     * @access protected
     * @return bool
     */
    protected function obj_db_error()
    {
        return ( pg_last_error( $this->obj_connection ) ) ? true : false;
    }

    /**
     * Returns error message for current connection instance
     *
     * @access protected
     * @return str
     */
    protected function obj_db_message()
    {
        return ( $this->obj_db_error() ) ? pg_last_error( $this->obj_connection ) : null;
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
        return pg_escape_string( $data );
    }

    /**
     * Returns database server information
     *
     * @access protected
     * @return array
     */
    protected function obj_db_info()
    {
        $version = pg_version( $this->obj_connection );

        return [$version['client'], pg_client_encoding( $this->obj_connection ), $this->obj_datasource[4]];
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
        return "SELECT $cols FROM $table $where $order_by LIMIT $limit OFFSET $offset";
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

		$query_stmt = pg_query( $this->obj_connection, "SELECT COUNT($cols) FROM $table $where" );

		if ( is_resource( $query_stmt ) )
		{
            $num_rows = pg_fetch_row( $query_stmt );
            $obj_return = ( $num_rows[0] >= 0 ) ? $num_rows[0] : -1;

            pg_free_result( $query_stmt );
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
        $query_stmt = pg_query( $this->obj_connection, $this->obj_query );

        return ( is_resource( $query_stmt ) ) ? new obj_resultset( $query_stmt ) : false;
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
     * Prepared statement binding parameters
     *
     * @access private
     * @var    array
     */
    private $obj_parameter = [];

    /**
     * Prepared statement instance
     *
     * @access private
     * @var    mixed
     */
    private $obj_prepare_instance;

    /**
     * Prepared statement name
     *
     * @access private
     * @var    str
     */
    private $obj_query_name;

    /**
     * Resultset resource
     *
     * @access private
     * @var    mixed
     */
    private $obj_result_resource;

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
        $this->obj_query_name = substr( MD5( microtime() ), 0, 12 );
        $this->obj_prepare_init( $query );
    }

    /**
     * Sets parameters for prepared statement
     *
     * @access public
     * @param  mixed $param
     */
    public function obj_bind( $param )
    {
        $this->obj_parameter[] = trim( $param );
    }

    /**
     * Destroys prepared statement object
     *
     * @access public
     * @return bool
     */
    public function obj_close_statement()
    {
        $query_stmt = pg_query( $this->obj_connection, "DEALLOCATE \"{$this->obj_query_name}\"" );

        return ( is_resource( $query_stmt ) ) ? true : false;
    }

    /**
     * Executes prepared statement and returns resultset resource
     *
     * @access public
     * @return mixed
     */
    public function obj_execute()
    {
        $this->obj_result_resource = pg_execute( $this->obj_connection, $this->obj_query_name, $this->obj_parameter );

        return ( is_resource( $this->obj_result_resource ) ) ? new obj_resultset( $this->obj_result_resource ) : false;
    }

    /**
     * Releases statement resource memory and resets binding parameters
     *
     * @access public
     * @return bool
     */
    public function obj_free_statement()
    {
        $this->obj_parameter = array();

        return pg_free_result( $this->obj_result_resource );
    }

    /**
     * Returns prepared statement resource
     *
     * @access private
     * @param  str $query
     * @return mixed
     */
    private function obj_prepare_init( $query  )
    {
        $this->obj_prepare_instance = pg_prepare( $this->obj_connection, $this->obj_query_name, $this->obj_prepare_sql( $query ) );

        return ( is_resource( $this->obj_prepare_instance ) ) ? $this->obj_prepare_instance : false;
    }

    /**
     * Replaces (?) markers in prepared statement query string with ($1,$2,etc)
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
            $query_sql .= "$$i {$query_parts[$i]}";
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
     * Resultset query record
     *
     * @access private
     * @var    array
     */
    private $obj_record = [];

    /**
     * Query result object
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
        $affected_rows = pg_affected_rows( $this->obj_result );

        return ( $affected_rows >= 0 ) ? $affected_rows : -1;
    }

    /**
     * Returns resultset resource as associative array
     *
     * @access public
     * @return mixed
     */
    public function obj_fetch_assoc()
    {
        $result = pg_fetch_assoc( $this->obj_result );

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
        $result = pg_fetch_row( $this->obj_result );

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
        $result = pg_fetch_object( $this->obj_result );

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
     * Frees resultset memory and destroys resultset object
     *
     * @access public
     * @return bool
     */
    public function obj_free_result()
    {
        $this->obj_record = [];

        return pg_free_result( $this->obj_result );
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
        $num_cols = pg_num_fields( $this->obj_result );

        return ( $num_cols >= 0 ) ? $num_cols : -1;
    }

    /**
     * Return number rows from query
     * Returns -1 if undetermined or failure
     *
     * @access public
     * @return int
     */
    public function obj_num_rows()
    {
        $num_rows = pg_num_rows( $this->obj_result );

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
     * Database connection object
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
        pg_query( $this->obj_connection, 'BEGIN' );
    }

    /**
     * Commits transaction for current transaction instance
     *
     * @access public
     * @return bool
     */
    public function obj_commit()
    {
        return ( is_resource( pg_query( $this->obj_connection, 'END' ) ) ) ? true : false;
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
        $rollback = ( !$savepoint ) ? 'ROLLBACK' : "ROLLBACK TO SAVEPOINT $savepoint";

        return ( is_resource( pg_query( $this->obj_connection, $rollback ) ) ) ? true : false;
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
        return ( is_resource( pg_query( $this->obj_connection, "SAVEPOINT $savepoint" ) ) ) ? true : false;
    }
}

/* EOF driver.php */
/* Location: ./drivers/pgsql/driver.php */