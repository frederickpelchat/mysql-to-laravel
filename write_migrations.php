#!/usr/bin/env php

<?php
/*
 * (c) Frederick Pelchat 2014-2019
 */

$migrations_dst_dir = '../laravel_app/database/migrations/';
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'toortoor';
$db_name = 'master';

#do not edit below this line
$daily_ndx = 000000;
$daily_ndx_inc = 1000;

function fopen_migration_file($table) {
  global $daily_ndx, $daily_ndx_inc, $migrations_dst_dir;
  $file = sprintf('%s_%06d_create_%s_table.php', @date('Y_m_d'), $daily_ndx, $table);
  $fp = fopen($migrations_dst_dir . $file, 'w');
  if(!$fp) {
    die('cannot open migration file');
  }
  $daily_ndx += $daily_ndx_inc;
  return $fp;
}

function migration_head($fp, $table) {
  fprintf($fp, "<?php\n\n");
  fprintf($fp, "use Illuminate\Support\Facades\Schema;\n");
  fprintf($fp, "use Illuminate\Database\Schema\Blueprint;\n");
  fprintf($fp, "use Illuminate\Database\Migrations\Migration;\n\n");
  $tbl = str_replace('_', '', $table);
  fprintf($fp, "class Create%sTable extends Migration\n", $tbl);
  fprintf($fp, "{\n");
  fprintf($fp, "  /**\n");
  fprintf($fp, "   * Run the migrations.\n");
  fprintf($fp, "   *\n");
  fprintf($fp, "   * @return void\n");
  fprintf($fp, "   */\n");
  fprintf($fp, "  public function up()\n");
  fprintf($fp, "  {\n");
  fprintf($fp, "    Schema::create('%s', function (Blueprint \$table) {\n", $table);
}

function create_table_tail($fp, $unique_keys = [], $indexes = []) {
  foreach($unique_keys as $uk) {
    if(count($uk) == 1) {
      fprintf($fp, "      \$table->unique('%s');\n", $uk[0]);
    } else { //compound key
      fprintf($fp, "      \$table->unique(['%s']);\n", implode("','", $uk));
    }
  }

  foreach($indexes as $ik) {
    if(count($ik) == 1) {
      fprintf($fp, "      \$table->index('%s');\n", $ik[0]);
    } else { //compound key
      fprintf($fp, "      \$table->index(['%s']);\n", implode("','", $ik));
    }
  }

//  fprintf($fp, "      \$table->timestamps();\n");
  fprintf($fp, "    });\n\n");
}

function migration_tail($fp, $table) {
  fprintf($fp, "  }\n\n");
  fprintf($fp, "  /**\n");
  fprintf($fp, "   * Reverse the migrations.\n");
  fprintf($fp, "   *\n");
  fprintf($fp, "   * @return void\n");
  fprintf($fp, "   */\n");
  fprintf($fp, "  public function down()\n");
  fprintf($fp, "  {\n");
  fprintf($fp, "    Schema::drop('%s');\n", $table);
  fprintf($fp, "  }\n");
  fprintf($fp, "}\n");
}

function create_table_column($fp, $column, $type = [], $unsigned = false, $null = false) {
  if(is_array($type)) {
    if(count($type) == 2) {
      fprintf($fp, "      \$table->%s('%s', %d)%s%s;\n", $type[0], $column, $type[1],
        ($unsigned? '->unsigned()' : ''),
        ($null? '->nullable()' : ''));
    } else if(count($type) == 3) { //decimal
      fprintf($fp, "      \$table->%s('%s', %d, %d)%s%s;\n", $type[0], $column, $type[1], $type[2],
        ($unsigned? '->unsigned()' : ''),
        ($null? '->nullable()' : ''));
    } else {
      //should never happens
    }
  } else {
    fprintf($fp, "      \$table->%s('%s')%s%s;\n", $type, $column,
      ($unsigned? '->unsigned()' : ''),
      ($null? '->nullable()' : ''));
  }
}

function create_table_foreign($fp, $table, $column, $referenced_table, $referenced_column) {
  fprintf($fp, "\n    Schema::table('%s', function (Blueprint \$table) {\n", $table);
  fprintf($fp, "      DB::statement('SET FOREIGN_KEY_CHECKS=0;');\n");
  fprintf($fp, "      /*\n");
  fprintf($fp, "       * Doesnt work because of some dumb behaviour\n");
  fprintf($fp, "       */\n");
  fprintf($fp, "      /*\n");
  fprintf($fp, "      \$table->foreign('%s')\n", $column);
  fprintf($fp, "        ->references('%s')->on('%s')\n", $referenced_table, $referenced_column);
  fprintf($fp, "        ->onDelete('cascade')\n");
  fprintf($fp, "        ->onUpdate('cascade');\n");
  fprintf($fp, "       */\n");
  fprintf($fp, "      DB::statement('ALTER TABLE `%s` 
ADD CONSTRAINT %s_%s_foreign 
FOREIGN KEY (`%s`) 
REFERENCES `%s` (`%s`) 
ON DELETE CASCADE 
ON UPDATE CASCADE');\n", $table, strtolower($table), strtolower($column), $column, $referenced_table, $referenced_column);
  fprintf($fp, "      DB::statement('SET FOREIGN_KEY_CHECKS=1;');\n");
  fprintf($fp, "   });\n");
}

$all_tables_sql_fmt = "
SELECT TABLE_NAME  
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE();
";

$describe_table_sql_fmt = "
SELECT COLUMN_NAME AS `Field`, 
COLUMN_TYPE AS `Type`, 
IS_NULLABLE AS `Null`, 
COLUMN_KEY AS `Key`, 
COLUMN_DEFAULT AS `Default`, 
EXTRA AS `Extra`
FROM COLUMNS  
WHERE TABLE_SCHEMA = DATABASE() AND 
TABLE_NAME = '%s';
";

$all_fk_sql_fmt = "
SELECT i.TABLE_NAME, 
i.CONSTRAINT_TYPE, 
i.CONSTRAINT_NAME, 
k.COLUMN_NAME, 
k.REFERENCED_TABLE_NAME, 
k.REFERENCED_COLUMN_NAME
FROM information_schema.TABLE_CONSTRAINTS i
LEFT JOIN information_schema.KEY_COLUMN_USAGE k ON i.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE i.CONSTRAINT_TYPE = 'FOREIGN KEY'
AND i.TABLE_SCHEMA = DATABASE()
AND i.TABLE_NAME = '%s';";

$all_uni_sql_fmt = "
SELECT i.CONSTRAINT_NAME, 
k.COLUMN_NAME 
FROM information_schema.TABLE_CONSTRAINTS i
LEFT JOIN information_schema.KEY_COLUMN_USAGE k ON i.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE i.CONSTRAINT_TYPE = 'UNIQUE'
AND i.TABLE_SCHEMA = DATABASE()
AND i.TABLE_NAME = '%s';";

$conn = mysqli_connect($db_host, $db_user, $db_pass);
mysqli_select_db($conn, $db_name);
$res = mysqli_query($conn, sprintf($all_tables_sql_fmt));
$rows = [];
while($row = mysqli_fetch_row($res)) {
  array_push($rows, $row);
}

$sorted = [];
foreach($rows as $row) {
  if(in_array($row[0], ['event_type','type'])) { //must be created before so that the foreign key can be added to the referencing table
    array_unshift($sorted, $row);
  } else {
    array_push($sorted, $row);
  }
}

foreach($sorted as $row) {
  foreach($row as $table) {
    //iterate over tables
    $fp = fopen_migration_file($table);
    migration_head($fp, $table);
    $res2 = mysqli_query($conn, sprintf($describe_table_sql_fmt, $table));

    //fetch all indexes for this table
    $index_those = [];
    $res3 = mysqli_query($conn, "show indexes from $table");
    while($row3 = mysqli_fetch_assoc($res3)) {
      if($row3['Non_unique'] == 0) {
        continue;
      }
      if(!isset($index_those[$row3['Key_name']])) {
        $index_those[$row3['Key_name']] = [];
      }
      $index_those[$row3['Key_name']][] = $row3['Column_name'];
    }

    //fetch all foreign keys for this table
    $ref_those = [];
    $res4 = mysqli_query($conn, sprintf($all_fk_sql_fmt, $table));
    while($row4 = mysqli_fetch_assoc($res4)) {
      $ref_those[$row4['COLUMN_NAME']] = ['column' => $row4['COLUMN_NAME'],
        'ref_table' => $row4['REFERENCED_TABLE_NAME'],
        'ref_column' => $row4['REFERENCED_COLUMN_NAME']];
    }

    //fetch all unique keys for this table
    $uni_those = [];
    $res5 = mysqli_query($conn, sprintf($all_uni_sql_fmt, $table));
    while($row5 = mysqli_fetch_assoc($res5)) {
      if(!isset($uni_those[$row5['CONSTRAINT_NAME']])) {
        $uni_those[$row5['CONSTRAINT_NAME']] = [];
      }
      $uni_those[$row5['CONSTRAINT_NAME']][] = $row5['COLUMN_NAME'];
    }

    //iterate over each column and add them to the migration file
    while($row2 = mysqli_fetch_assoc($res2)) {
      $curr = [];

      $curr['unsigned'] = (bool)preg_match('/unsigned/i', $row2['Type']);
      $curr['primary'] = (bool)preg_match('/pri/i', $row2['Key']);

      $matches = [];

      if(preg_match('/^int/i', $row2['Type'])) {
        $curr['type'] = ($curr['primary']? 'increments': 'integer');
      } else if(preg_match('/^bigint/i', $row2['Type'])) {
        $curr['type'] = ($curr['primary']? 'bigIncrements': 'bigInteger');
      } else if(preg_match('/^varchar\s*\((\d+)\)/i', $row2['Type'], $matches)) {
        $curr['type'] = array('string', $matches[1]);
      } else if(preg_match('/^char\s*\((\d+)\)/i', $row2['Type'], $matches)) {
        $curr['type'] = array('char', $matches[1]);
      } else if(preg_match('/^timestamp/i', $row2['Type'])) {
        $curr['type'] = 'timestamp';
      } else if(preg_match('/^text/i', $row2['Type'])) {
        $curr['type'] = 'text';
      } else if(preg_match('/^tinyint\s*\((\d+)\)/i', $row2['Type'], $matches)) {
        if($matches[1] == 1) {
          $curr['type'] = 'boolean';
        } else {
          $curr['type'] = 'tinyInteger';
        }
      } else if(preg_match('/^decimal\s*\((\d+)\s*,\s*(\d+)\)/i', $row2['Type'], $matches)) {
        $curr['type'] = array('decimal', $matches[1], $matches[2]);
      /*
       * TODO Add new types here
       */
      } else {
        printf("skipping unknown type %s\n", $row2['Type']);
        continue;
      }

      $curr['column'] = $row2['Field'];
      $curr['null'] = ($row2['Null'] == 'YES');

      create_table_column($fp, $curr['column'], $curr['type'], $curr['unsigned'], $curr['null']);
    }

    create_table_tail($fp, $uni_those, $index_those);

    foreach($ref_those as $fk) {
      create_table_foreign($fp, $table, $fk['column'], $fk['ref_table'], $fk['ref_column']);
    }

    migration_tail($fp, $table);
    fclose($fp);
  }
}

?>
