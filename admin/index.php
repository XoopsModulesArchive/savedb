<?php

require_once 'admin_header.php';

$strNoTablesFound = _MA_SAVEDB_NOTABLE;
$strHost = _MA_SAVEDB_HOST;
$strDatabase = _MA_SAVEDB_DB;
$strTableStructure = _MA_SAVEDB_STR;
$strDumpingData = _MA_SAVEDB_DUMP;
$strError = _MA_SAVEDB_ERROR;
$strSQLQuery = _MA_SAVEDB_QUERY;
$strMySQLSaid = _MA_SAVEDB_MYSAID;
$strBack = _MA_SAVEDB_BACK;
$strFileName = _MA_SAVEDB_FILENAME;
$strName = _MA_SAVEDB_NAME;
$strDone = _MA_SAVEDB_DONE;
$strat = _MA_SAVEDB_AT;
$date_jour = _MA_SAVEDB_DATE;

global $db, $xoopsconfig, $dbhost, $dbuname, $dbpass, $dbname;

$dbhost = XOOPS_DB_HOST;
$dbuname = XOOPS_DB_USER;
$dbpass = XOOPS_DB_PASS;
$dbname = XOOPS_DB_NAME;

@set_time_limit(600);
$crlf = "\n";

header("Content-disposition: filename=$strFileName $dbname $date_jour.sql");
header('Content-type: application/octetstream');
header('Pragma: no-cache');
header('Expires: 0');

// doing some DOS-CRLF magic...
$client = getenv('HTTP_USER_AGENT');
if (preg_match('[^(]*\((.*)\)[^)]*', $client, $regs)) {
    $os = $regs[1];

    // this looks better under WinX

    if (eregi('Win', $os)) {
        $crlf = "\r\n";
    }
}

function myHandler($sql_insert)
{
    global $crlf;

    echo "$sql_insert;$crlf";
}

// Get the content of $table as a series of INSERT statements.
// After every row, a custom callback function $handler gets called.
// $handler must accept one parameter ($sql_insert);
function get_table_content($db, $table, $handler)
{
    $result = mysql_db_query($db, "SELECT * FROM $table") or mysql_die();

    $i = 0;

    while (false !== ($row = $GLOBALS['xoopsDB']->fetchRow($result))) {
        //        set_time_limit(60); // HaRa

        $table_list = '(';

        for ($j = 0; $j < mysqli_num_fields($result); $j++) {
            $table_list .= $GLOBALS['xoopsDB']->getFieldName($result, $j) . ', ';
        }

        $table_list = mb_substr($table_list, 0, -2);

        $table_list .= ')';

        if (isset($GLOBALS['showcolumns'])) {
            $schema_insert = "INSERT INTO $table $table_list VALUES (";
        } else {
            $schema_insert = "INSERT INTO $table VALUES (";
        }

        for ($j = 0; $j < mysqli_num_fields($result); $j++) {
            if (!isset($row[$j])) {
                $schema_insert .= ' NULL,';
            } elseif ('' != $row[$j]) {
                $schema_insert .= " '" . addslashes($row[$j]) . "',";
            } else {
                $schema_insert .= " '',";
            }
        }

        $schema_insert = preg_replace(',$', '', $schema_insert);

        $schema_insert .= ')';

        $handler(trim($schema_insert));

        $i++;
    }

    return (true);
}

// Return $table's CREATE definition
// Returns a string containing the CREATE statement on success
function get_table_def($db, $table, $crlf)
{
    $schema_create = '';

    $schema_create .= "DROP TABLE IF EXISTS $table;$crlf";

    $schema_create .= "CREATE TABLE $table ($crlf";

    $result = mysql_db_query($db, "SHOW FIELDS FROM $table") or mysql_die();

    while (false !== ($row = $GLOBALS['xoopsDB']->fetchBoth($result))) {
        $schema_create .= "   $row[Field] $row[Type]";

        if (isset($row['Default']) && (!empty($row['Default']) || '0' == $row['Default'])) {
            $schema_create .= " DEFAULT '$row[Default]'";
        }

        if ('YES' != $row['Null']) {
            $schema_create .= ' NOT NULL';
        }

        if ('' != $row['Extra']) {
            $schema_create .= " $row[Extra]";
        }

        $schema_create .= ",$crlf";
    }

    $schema_create = preg_replace(',' . $crlf . '$', '', $schema_create);

    $result = mysql_db_query($db, "SHOW KEYS FROM $table") or mysql_die();

    while (false !== ($row = $GLOBALS['xoopsDB']->fetchBoth($result))) {
        $kname = $row['Key_name'];

        if (('PRIMARY' != $kname) && (0 == $row['Non_unique'])) {
            $kname = "UNIQUE|$kname";
        }

        if (!isset($index[$kname])) {
            $index[$kname] = [];
        }

        $index[$kname][] = $row['Column_name'];
    }

    while (list($x, $columns) = @each($index)) {
        $schema_create .= ",$crlf";

        if ('PRIMARY' == $x) {
            $schema_create .= '   PRIMARY KEY (' . implode(', ', $columns) . ')';
        } elseif ('UNIQUE' == mb_substr($x, 0, 6)) {
            $schema_create .= '   UNIQUE ' . mb_substr($x, 7) . ' (' . implode(', ', $columns) . ')';
        } else {
            $schema_create .= "   KEY $x (" . implode(', ', $columns) . ')';
        }
    }

    $schema_create .= "$crlf)";

    return (stripslashes($schema_create));
}

function mysql_die($error = '')
{
    echo "<b> $strError </b><p>";

    if (isset($sql_query) && !empty($sql_query)) {
        echo "$strSQLQuery: <pre>$sql_query</pre><p>";
    }

    if (empty($error)) {
        echo $strMySQLSaid . $GLOBALS['xoopsDB']->error();
    } else {
        echo $strMySQLSaid . $error;
    }

    echo "<br><a href=\"javascript:history.go(-1)\">$strBack</a>";

    exit;
}

mysql_pconnect($dbhost, $dbuname, $dbpass);
@mysqli_select_db($GLOBALS['xoopsDB']->conn, (string)$dbname) or die('Unable to select database');

$tables = mysql_list_tables($dbname);

$num_tables = @mysql_numrows($tables);
if (0 == $num_tables) {
    echo $strNoTablesFound;
} else {
    $i = 0;

    $heure_jour = date('H:i');

    print "# ========================================================$crlf";

    print "#$crlf";

    print "# $strName : $dbname$crlf";

    print "# $strDone $date_jour $strat $heure_jour !$crlf";

    print "#$crlf";

    print "# ========================================================$crlf";

    print (string)$crlf;

    while ($i < $num_tables) {
        $table = mysql_tablename($tables, $i);

        print $crlf;

        print "# --------------------------------------------------------$crlf";

        print "#$crlf";

        print "# $strTableStructure '$table'$crlf";

        print "#$crlf";

        print $crlf;

        echo get_table_def($dbname, $table, $crlf) . ";$crlf$crlf";

        print "#$crlf";

        print "# $strDumpingData '$table'$crlf";

        print "#$crlf";

        print $crlf;

        get_table_content($dbname, $table, 'myHandler');

        $i++;
    }
}

break;
