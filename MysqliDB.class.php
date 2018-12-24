<?php
header('content-type:text/html;charset=utf-8');
/*
掌握满足单例模式的必要条件
(1)私有的构造方法-为了防止在类外使用new关键字实例化对象
(2)私有的成员属性-为了防止在类外引入这个存放对象的属性
(3)私有的克隆方法-为了防止在类外通过clone成生另一个对象
(4)公有的静态方法-为了让用户进行实例化对象的操作
*/

class MysqliDB{
    //私有的属性
    private static $dbcon = false;
    private $host;
    private $port;
    private $user;
    private $pass;
    private $db;
    private $charset;
    private $link;

    //私有化构造方法
    private function __construct($config = array()){
        $this->host = isset($config['host']) ? $config['host'] : 'localhost';
        $this->port = isset($config['port']) ? $config['port'] : '3306';
        $this->user = isset($config['user']) ? $config['user'] : 'root';
        $this->pass = isset($config['pass']) ? $config['pass'] : 'root';
        $this->db = isset($config['db']) ? $config['db'] : 'ecar';
        $this->charset = isset($config['charset']) ? $config['charset'] : 'utf8';
        //连接数据库
        $this->db_connect();
        //选择数据库
        $this->db_usedb();
        //设置字符集
        $this->db_charset();
    }
    
    //连接数据库
    private function db_connect(){
        $this->link = mysqli_connect($this->host . ':' . $this->port, $this->user, $this->pass);
        if (!$this->link) {
            echo "数据库连接失败<br>";
            echo "错误编码" . mysqli_errno($this->link) . "<br>";
            echo "错误信息" . mysqli_error($this->link) . "<br>";
            exit;
        }
    }
    //设置字符集
    private function db_charset(){
        mysqli_query($this->link, "set names {$this->charset}");
    }
    //选择数据库
    private function db_usedb(){
        mysqli_query($this->link, "use {$this->db}");
    }
    //私有的克隆
    private function __clone(){
        die('clone is not allowed');
    }
    
    //公用的静态方法
    public static function getIntance(){
        if (self::$dbcon == false) {
            self::$dbcon = new self;
        }
        return self::$dbcon;
    }
    
    //执行sql语句的方法
    public function query($sql){
        $res = mysqli_query($this->link, $sql);
        if (!$res) {
            echo "sql语句执行失败<br>";
            echo "错误编码是" . mysqli_errno($this->link) . "<br>";
            echo "错误信息是" . mysqli_error($this->link) . "<br>";
        }
        return $res;
    }
    //打印数据
    public function p($arr){
        echo "<pre>";
        print_r($arr);
        echo "</pre>";
    }
    public function v($arr){
        echo "<pre>";
        var_dump($arr);
        echo "</pre>";
    }
    
    //获得最后一条记录id
    public function getInsertid(){
        return mysqli_insert_id($this->link);
    }
    
    /**
     * 查询某个字段的值
     * @param1 string $sql  	 sql语句
	 * @param2 string $field  字段
     * @return string or int
     */
    public function getField($sql, $field){
        $query = $this->query($sql);
        $row = mysqli_fetch_assoc($query);
        return $row[$field];
    }
    

    /*
    * 查询一条记录
    * @param1 $sql  表名  查询语句
    * @return array 返回查询的结果
    */
    public function getRow($table, $where, $type = "assoc"){
    	if (is_array($where)) {
    		foreach ($where as $key => $val) {
    			$arr[] = $key . '=' . "'" .$val."'";
    		}
    		$condition = implode(" and ", $arr);
    	} else {
    		$condition = $where;
    	}
    	$sql = "select * from $table where $condition";
        $query = $this->query($sql);
        if (!in_array($type, array(
            "assoc",
            'array',
            "row"))) {
            die("mysqli_query error");
        }
        $funcname = "mysqli_fetch_" . $type;
        return $funcname($query);  //返回的是一维数组
    }
    
    //获取一条记录,前置条件通过资源获取一条记录
    public function getFormSource($query, $type = "assoc"){
        if (!in_array($type, array(
            "assoc",
            "array",
            "row"))) {
            die("mysqli_query error");
        }
        $funcname = "mysqli_fetch_" . $type;
        return $funcname($query);
    }
    
    /*
    * 通过SQL语句获得查询结果
    * @param1 string $sql  表名  查询语句
    * @return array 返回查询的结果
    */
    public function getQuery($sql){
    	$query = $this->query($sql);
    	$list = array();
    	while ($result = $this->getFormSource($query)) {
    		$list[] = $result;
    	}
    	return $list;
    }
    

    /*
     * 获取多条数据，二维数组
    * @param1 string $table,  表名
    * @return array 返回查询的结果
    */
    public function getAll($table){
    	$sql = "select * from $table";
        $query = $this->query($sql);
        $list = array();
        while ($result = $this->getFormSource($query)) {
            $list[] = $result;
        }
        return $list;
    }
    /**
     * 定义添加数据的方法
     * @param1 string $table 表名
     * @param2 array $data 需要更新的数据
     * @return int 最新添加的id
     */
    public function insert($table, $data){
        //遍历数组，得到每一个字段和字段的值
        $key_str = '';
        $v_str = '';
        foreach ($data as $key => $v) {
             if (!empty($v)) {       //如果值不为空    false  ""  都判断为true, 则应该排除在外
	            $key_str .= $key . ',';
	            $v_str .= "'$v',";
            }
        }
        $key_str = trim($key_str, ',');
        $v_str = trim($v_str, ',');
        //判断数据是否为空
        $sql = "insert into $table ($key_str) values ($v_str)";
        $this->query($sql);
        //返回上一次增加操做产生ID值
        return $this->getInsertid();
    }
    /*
    * 删除一条数据方法
    * @param1 $table  表名    
    * @param2 string or array $where=array('id'=>'1') 条件
    * @return  int 受影响的行数
    */
    public function deleteOne($table, $where){
        if (is_array($where)) {
    		foreach ($where as $key => $val) {
    			$arr[] = $key . '=' . "'" .$val."'";
    		}
    		$condition = implode(" and ", $arr);
    	} else {
    		$condition = $where;
    	}
        $sql = "delete from $table where $condition";
        $this->query($sql);
        //返回受影响的行数
        return mysqli_affected_rows($this->link);
    }
    /*
    * 删除多条数据方法
    * @param1 string $table 表名
    * @param2 string or array $where 条件   格式  array("id"=>array(1, 2, 3, 4, 5))
    * @return int 受影响的行数
    */
    public function deleteAll($table, $where){
	    if (is_array($where)) {
	        foreach($where as $key => $val){
	            if (is_array($val)) {
	                $condition = $key . ' in (' . implode(',', $val) . ')';
	            }else{
	                $condition = $key . '=' . $val;
	            }
	        }
	    }else{
	        $condition = $where;
	    }
        $sql = "delete from $table where $condition";
        $this->query($sql);
        //返回受影响的行数
        return mysqli_affected_rows($this->link);
    }
    /**
     * 修改操作description
     * @param1 string $table 表名
     * @param2 array $data   更新的数据
     * @param3 string or array $where 条件
     * @return4 int 影响的记录数
     */
     public function update($table, $data, $where){
        //遍历数组，得到每一个字段和字段的值
        $str = '';
        foreach ($data as $key => $v) {
            $str .= "$key='$v',";
        }
        $str = rtrim($str, ',');
    	if (is_array($where)) {
    		foreach ($where as $key => $val) {
    			$arr[] = $key . '=' . "'" .$val."'";
    		}
    		$condition = implode(" and ", $arr);
    	} else {
    		$condition = $where;
    	}
        //修改SQL语句
        $sql = "update $table set $str where $condition";
        $this->query($sql);
        //返回受影响的行数
        return mysqli_affected_rows($this->link);
        //return $condition;
    }
}



//用法测试：    
//mysqli测试
$link = MysqliDB::getIntance();
$sql="select * from ims_ewei_fanhua_record where id = 40";
$one = $link->getField($sql, 'taskId');
echo "<pre>";
echo $one;
echo "</pre>";


echo "测试全部";


$list=$link->getAll($sql);

echo "<pre>";
print_r($list);
echo "</pre>";




?>
