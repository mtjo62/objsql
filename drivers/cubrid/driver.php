<?php

/**
 * CUBRID database server access classes
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
        $obj_connection = cubrid_connect( $this->obj_datasource[1], $this->obj_datasource[5], $this->obj_datasource[4], $this->obj_datasource[2], $this->obj_datasource[3] );

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
        return cubrid_disconnect( $this->obj_connection );
    }

    /**
     * Returns error flag for current connection instance
     *
     * @access protected
     * @return bool
     */
    protected function obj_db_error()
    {
        return ( cubrid_error_code() !== 0 ) ? true : false;
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
        return cubrid_real_escape_string( $data );
    }

    /**
     * Returns error message for current connection instance
     *
     * @access protected
     * @return mixed
     */
    protected function obj_db_message()
	{
        return ( $this->obj_db_error() ) ? cubrid_error_msg() : null;
    }

    /**
     * Returns database server information
     *
     * @access protected
     * @return array
     */
    protected function obj_db_info()
    {
        return [cubrid_get_server_info( $this->obj_connection ), cubrid_get_charset( $this->obj_connection ), $this->obj_datasource[4]];
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
        return "SELECT $cols FROM $table $where $order_by LIMIT $offset,$limit";
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

        $query_stmt = cubrid_execute( $this->obj_connection, "SELECT COUNT($cols) FROM $table $where", CUBRID_EXEC_QUERY_ALL );

        if ( is_resource( $query_stmt ) )
        {
            $num_rows = cubrid_fetch( $query_stmt, CUBRID_NUM );
			$obj_return = ( $num_rows[0] >= 0 ) ? $num_rows[0] : -1;

            cubrid_close_request( $query_stmt );
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
     * Executes general query and returns resultset object
     *
     * @access public
     * @return mixed
     */
    public function obj_query_execute()
    {
        $query_stmt = cubrid_execute( $this->obj_connection, $this->obj_query, CUBRID_EXEC_QUERY_ALL );

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
     * Set obj_bind parameter counter
     *
     * @access private
     * @var    int
     */
    private $obj_parameter_cnt = 0;

   /**
     * Prepared statement instance
     *
     * @access public
     * @var    bool
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
     */
    function __construct( $query, $connection )
    {
        $this->obj_connection = $connection->obj_connection;
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
        $this->obj_parameter_cnt++;

        return cubrid_bind( $this->obj_prepare_instance, $this->obj_parameter_cnt, $param );
    }

    /**
     * Destroys prepared statement object
     *
     * @access public
     * @return bool
     */
    public function obj_close_statement()
    {
        return ( cubrid_close_prepare( $this->obj_prepare_instance ) ) ? true : false;
    }

    /**
     * Executes prepared statement and returns resultset object
     *
     * @access public
     * @return mixed
     */
    public function obj_execute()
    {
        $query_stmt = cubrid_execute( $this->obj_prepare_instance );

        return ( $query_stmt ) ? new obj_resultset( $this->obj_prepare_instance, $this->obj_connection ) : false;
    }

    /**
     * Frees resultset memory from prepared statement object and resets binding parameters
     *
     * @access public
     * @return bool
     */
    public function obj_free_statement()
    {
        $this->obj_parameter_cnt = 0;

        return cubrid_free_result( $this->obj_prepare_instance );
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
        $prepare_instance = cubrid_prepare( $this->obj_connection, $query );

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
        $affected_rows = cubrid_affected_rows( $this->obj_connection );

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
        $result = cubrid_fetch( $this->obj_result, CUBRID_ASSOC );

        return ( is_array( $result ) && $result !== null && $result !== false ) ? $this->obj_record = $result : null;
    }

    /**
     * Returns resultset object as numeric array
     *
     * @access public
     * @return mixed
     */
    public function obj_fetch_num()
    {
        $result = cubrid_fetch( $this->obj_result, CUBRID_NUM );

        return ( is_array( $result ) && $result !== null && $result !== false ) ? $this->obj_record = $result : null;
    }

    /**
     * Returns resultset object
     *
     * @access public
     * @return mixed
     */

    public function obj_fetch_object()
    {
        $result = cubrid_fetch( $this->obj_result, CUBRID_OBJECT );

        return ( is_object( $result ) && $result !== null && $result !== false ) ? $this->obj_record = $result : null;
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
            return ( get_magic_quotes_runtime() ) ? stripslashes( $this->obj_record[$field] ) : $this->obj_record[$field];
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

        return ( cubrid_close_request( $this->obj_result ) ) ? true : false;
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
        $num_cols = cubrid_num_cols( $this->obj_result );

        return ( $num_cols !== false && $num_cols >= 0 ) ? $num_cols : -1;
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
        $num_rows = cubrid_num_rows( $this->obj_result );

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
        cubrid_set_autocommit( $this->obj_connection, CUBRID_AUTOCOMMIT_FALSE );
    }

    /**
     * Commits transaction for current transaction instance
     *
     * @access public
     * @return bool
     */
    public function obj_commit()
    {
        return cubrid_commit( $this->obj_connection );
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
			$rollback = cubrid_rollback( $this->obj_connection );
        else
            $rollback = cubrid_execute( $this->obj_connection, "ROLLBACK WORK TO SAVEPOINT $savepoint" );
 
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
        $rollback = cubrid_execute( $this->obj_connection, "SAVEPOINT $savepoint" );

        return ( is_resource( $rollback ) ) ? true : false;
    }
}

/* EOF driver.php */
/* Location: ./drivers/cubrid/driver.php */