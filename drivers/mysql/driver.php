<?php

/**
 * MySQL/MariaDB database server access classes
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
        $obj_port = ( array_key_exists( 5, $this->obj_datasource ) ) ? (int)$this->obj_datasource[5] : false;

        if ( $obj_port )
			$obj_connection = new mysqli( $this->obj_datasource[1], $this->obj_datasource[2], $this->obj_datasource[3], $this->obj_datasource[4], $obj_port );
        else
            $obj_connection = new mysqli( $this->obj_datasource[1], $this->obj_datasource[2], $this->obj_datasource[3], $this->obj_datasource[4] );

        return ( is_object( $obj_connection ) ) ? $this->obj_connection = $obj_connection : false;
    }

    /**
     * Closes connection to database server
     *
     * @access protected
     * @return bool
     */
    protected function obj_db_close()
    {
        return $this->obj_connection->close();
    }

    /**
     * Returns PHP extension type
     *
     * @access protected
     * @return str
     */
    protected function obj_db_driver()
    {
        return ( $this->obj_pdo ) ? 'php_pdo_mysql' : 'php_mysqli';
    }

    /**
     * Returns error flag for current connection instance
     *
     * @access protected
     * @return bool
     */
    protected function obj_db_error()
    {
        return ( $this->obj_connection->errno ) ? true : false;
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
        return $this->obj_connection->real_escape_string( $data );
    }

    /**
     * Returns error message for current connection instance
     *
     * @access protected
     * @return mixed
     */
    protected function obj_db_message()
    {
        return ( $this->obj_db_error() ) ? $this->obj_connection->error : null;
    }

    /**
     * Returns database server information
     *
     * @access protected
     * @return array
     */
    protected function obj_db_info()
    {
        return [$this->obj_connection->server_version, $this->obj_connection->character_set_name(), $this->obj_datasource[4]];
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

		$query_stmt = $this->obj_connection->query( "SELECT COUNT($cols) FROM $table $where" );

		if ( $query_stmt || is_object( $query_stmt ) )
		{
            $num_rows = $query_stmt->fetch_row();
			$obj_return = ( $num_rows[0] >= 0 ) ? $num_rows[0] : -1;
            
            $query_stmt->free();
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
        $query_stmt = $this->obj_connection->query( $this->obj_query );

        return ( $query_stmt || is_object( $query_stmt ) ) ? new obj_resultset( $query_stmt, $this->obj_connection ) : false;
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
     * Prepared statement parameter types
     *
     * @access private
     * @var    array
     */
    private $obj_parameter_type = [];

    /**
     * Prepared statement instance
     *
     * @access public
     * @var    bool
     */
    private $obj_prepare_instance;

    /**
     * Prepared statement parameter types
     *
     * @access private
     * @var    array
     */
    private $obj_query_type;

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
     * Sets parameters and parameter types for prepared statement
     *
     * @access public
     * @param  mixed $param
     */
    public function obj_bind( $param )
    {
        $this->obj_parameter_type[] = $this->obj_param_type( $param );
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
        return $this->obj_prepare_instance->close();
    }

    /**
     * Executes prepared statement and returns resultset object
     *
     * @access public
     * @return mixed
     */
    public function obj_execute()
    {
        //default return value
        $obj_return = false;

        call_user_func_array( 'mysqli_stmt_bind_param', array_merge([$this->obj_prepare_instance, implode( '', $this->obj_parameter_type )], $this->obj_param_values() ) );
        $query_stmt = $this->obj_prepare_instance->execute();
        $query_result = $this->obj_prepare_instance->get_result();

        if ( $query_stmt )
        {
            if ( !$query_result )
				$obj_return = new obj_resultset( $query_stmt, $this->obj_connection );
            else
                $obj_return = new obj_resultset( $query_result, $this->obj_connection );
        }

        return $obj_return;
    }

    /**
     * Frees resultset memory from prepared statement object and resets binding parameters
     *
     * @access public
     * @return bool
     */
    public function obj_free_statement()
    {
        $this->obj_parameter = [];
        $this->obj_parameter_type = [];

        return $this->obj_prepare_instance->reset();
    }

    /**
     * Returns parameter datatype
     *
     * @access private
     * @param  mixed $param
     * @return mixed
     */
    private function obj_param_type( $param )
    {
        //default return value
        $obj_return = 'b';

        $param = trim( $param );

        if ( is_numeric( $param ) )
			$obj_return = ( substr_count( $param, '.' ) ) ? 'd' : 'i';
        elseif ( is_string( $param ) )
            $obj_return = 's';

        return $obj_return;
    }

    /**
     * Returns by reference bound parameter values
     *
     * @access private
     * @return array
     */
    private function obj_param_values()
    {
        $param = [];
        $value = null;

        foreach( $this->obj_parameter as $key => $value )
			$param[$key] = &$this->obj_parameter[$key];

        return $param;
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
        return $this->obj_prepare_instance = $this->obj_connection->prepare( $query );
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
        $affected_rows = $this->obj_connection->affected_rows;

        return ( $affected_rows !== null && $affected_rows >= 0 ) ? $affected_rows : -1;
    }

    /**
     * Returns resultset object as associative array
     *
     * @access public
     * @return mixed
     */
    public function obj_fetch_assoc()
    {
        $result = $this->obj_result->fetch_assoc();

        return ( is_array( $result ) && $result !== null ) ? $this->obj_record = $result : null;
    }

    /**
     * Returns resultset object as numeric array
     *
     * @access public
     * @return mixed
     */
    public function obj_fetch_num()
    {
        $result = $this->obj_result->fetch_row();

        return ( is_array( $result ) && $result !== null ) ? $this->obj_record = $result : null;
    }

    /**
     * Returns resultset object as object
     *
     * @access public
     * @return mixed
     */

    public function obj_fetch_object()
    {
        $result = $this->obj_result->fetch_object();

        return ( is_object( $result ) && $result !== null ) ? $this->obj_record = $result : null;
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
        $this->obj_result->free();
        $this->obj_record = [];

        return ( !is_object( $this->obj_result = null ) ) ? true : false;
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
        $num_cols = $this->obj_result->field_count;

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
        $num_rows = $this->obj_result->num_rows;

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
        $this->obj_connection->autocommit( false );
    }

    /**
     * Commits transaction for current transaction instance
     *
     * @access public
     * @return bool
     */
    public function obj_commit()
    {
        return $this->obj_connection->commit();
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
			$rollback = $this->obj_connection->rollback();
        else
            $rollback = $this->obj_connection->query( "ROLLBACK TO SAVEPOINT $savepoint" );

        return ( $rollback ) ? true : false;
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
        return ( $this->obj_connection->query( "SAVEPOINT $savepoint" ) ) ? true : false;
    }
}

/* EOF driver.php */
/* Location: ./drivers/mysql/driver.php */