<?php

/**
 * SQLite3 database server access classes
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
     * Database connection instance
     *
     * @access private
     * @var mixed
     */
    private $obj_connection;

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
        $this->obj_datasource = $datasource[4];
    }

    /**
     * Returns database connection object
     *
     * @access protected
     * @return mixed
     */
    protected function obj_db_connection()
    {
        $obj_connection = new SQLite3( $this->obj_datasource );

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
     * Returns error flag for current connection object
     *
     * @access protected
     * @return bool
     */
    protected function obj_db_error()
    {
        return ( $this->obj_connection->lastErrorCode() ) ? true : false;
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
        return $this->obj_connection->escapeString( $data );
    }

    /**
     * Returns error message for current connection object
     *
     * @access protected
     * @return str
     */
    protected function obj_db_message()
    {
        return ( $this->obj_db_error() ) ? $this->obj_connection->lastErrorMsg() : null;
    }

    /**
     * Returns database server information
     *
     * @access protected
     * @return array
     */
    protected function obj_db_info()
    {
        $query_stmt = $this->obj_connection->query( 'PRAGMA encoding' );
        
        return [$this->obj_connection->version()['versionString'], $query_stmt->fetchArray()['encoding'], $this->obj_datasource];
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
            $num_rows = $query_stmt->fetchArray( SQLITE3_NUM );
            $obj_return = ( $num_rows[0] >= 0 ) ? $num_rows[0] : -1;

            $query_stmt->finalize();
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
     * Set obj_bind parameter counter
     *
     * @access private
     * @var    int
     */
    private $obj_parameter_cnt = 0;

    /**
     * Database connection object
     *
     * @access private
     * @var    mixed
     */
    private $obj_connection;

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
     * Set parameters for prepared statement
     *
     * @access public
     * @param  mixed $param
     * @return bool
     */
    public function obj_bind( $param )
    {
        $this->obj_parameter_cnt++;

        return $this->obj_prepare_instance->bindValue( $this->obj_parameter_cnt, $param );
    }

    /**
     * Destroys prepared statement object
     *
     * @access public
     * @return bool
     */
    public function obj_close_statement()
    {
        return ( $this->obj_prepare_instance->close() ) ? true : false;
    }

    /**
     * Executes prepared statement and returns resultset object
     *
     * @access public
     * @return mixed
     */
    public function obj_execute()
    {
        $query_stmt = $this->obj_prepare_instance->execute();

        return ( is_object( $query_stmt ) ) ? new obj_resultset( $query_stmt, $this->obj_connection ) : false;
    }

    /**
     * Resets prepared statement object and binding parameters
     *
     * @access public
     * @return bool
     */
    public function obj_free_statement()
    {
        $this->obj_parameter_cnt = 0;
        $this->obj_prepare_instance->clear();

        return $this->obj_prepare_instance->reset();
    }

    /**
     * Returns prepared statement object
     *
     * @access private
     * @param  str $query
     * @return mixed
     */
    private function obj_prepare_init( $query )
    {
        $prepare_instance = $this->obj_connection->prepare( $query );

        return ( is_object( $prepare_instance ) ) ? $this->obj_prepare_instance = $prepare_instance : false;
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
     * @var mixed
     */
    private $obj_connection;

    /**
     * Query record
     *
     * @access private
     * @var array
     */
    private $obj_record = [];

    /**
     * Query resultset object
     *
     * @access private
     * @var mixed
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
     * @param  mixed $connection
     */
    public function __construct( $result, $connection )
    {
        $this->obj_connection = $connection;
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
        $affected_rows = $this->obj_connection->changes();

        return ( $affected_rows >= 0 ) ? $affected_rows : -1;
    }
    
    public function obj_rewind()
    {
        
       return $this->obj_result->reset();
    }

    /**
     * Returns resultset object as associative array
     *
     * @access public
     * @return mixed
     */
    public function obj_fetch_assoc()
    {
        $result = $this->obj_result->fetchArray( SQLITE3_ASSOC );

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
        $result = $this->obj_result->fetchArray( SQLITE3_NUM );

        return ( is_array( $result ) && $result !== false ) ? $this->obj_record = $result : null;
    }

    /**
     * Return resultset object as object
     *
     * @access public
     * @return mixed
     */
    public function obj_fetch_object()
    {
        $result = $this->obj_result->fetchArray( SQLITE3_ASSOC );

        return ( is_array( $result ) && $result !== false ) ? $this->obj_record = ( object )$result : null;
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

        return ( $this->obj_result->finalize() ) ? true : false;
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
        $num_cols = $this->obj_result->numColumns();

        return ( $num_cols >= 0 ) ? $num_cols : -1;
    }

    /**
     * Return number of rows from query
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

        while ( $this->obj_result->fetchArray( SQLITE3_NUM ) )
        {
            $num_rows++;
        }
        
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
        $this->obj_connection->exec( 'BEGIN' );
    }

    /**
     * Commits transaction for current transaction instance
     *
     * @access public
     * @return bool
     */
    public function obj_commit()
    {
        return $this->obj_connection->exec( 'COMMIT' );
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
        $rollback = ( !$savepoint ) ? 'ROLLBACK' : "ROLLBACK TO $savepoint";

        return $this->obj_connection->exec( $rollback );
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
        return $this->obj_connection->exec( "SAVEPOINT $savepoint" );
    }
}

/* EOF driver.php */
/* Location: ./drivers/sqlite/driver.php */