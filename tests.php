<?php
	include "./ssql.php";

	if(file_exists("./test.sqlite3"))
		unlink("./test.sqlite3");
	if(file_exists("./test2.sqlite3"))
		unlink("./test2.sqlite3");
	$ssql = new SSQL("./test.sqlite3");
	$ssql->debug = true;

	$tables = $ssql->tableList();
	assert(count($tables)==0);

	$ssql->createTable("myTable", [
		'id' => [
			'type' => "INT"
		],
		'name' => [
			'type' => "VARCHAR",
			'size' => 16
		],
		'text' => [
			'type' => "VARCHAR",
			'size' => 256,
			'NULL' => true
		]
	], "id");
	$tables = $ssql->tableList();
	assert(count($tables)==1 && $tables[0]=="myTable");

	$r = $ssql->read("myTable");
	assert(count($r)==0);
	$cols = $r->getColumns();
	assert(count($cols)==3);
	assert($cols[0]->name=="id");
	assert($cols[0]->type=="INT");
	assert($cols[0]->size==NULL);
	assert($cols[1]->name=="name");
	assert($cols[1]->type=="VARCHAR");
	assert($cols[1]->size==16);
	assert($cols[2]->name=="text");
	assert($cols[2]->type=="VARCHAR");
	assert($cols[2]->size==256);

	$ssql->beginTransaction();
	$r = $ssql->read("myTable");
	assert(count($r)==0);
	$ssql->put("myTable", [1, "Foo", "Bar"]);
	$r = $ssql->read("myTable");
	assert(count($r)==1);
	assert($r[0]->id==1);
	assert($r[0]->name=="Foo");
	assert($r[0]->text=="Bar");
	assert($r->id==1);
	assert($r->name=="Foo");
	assert($r->text=="Bar");
	$ssql->rollBackTransaction();
	assert(count($ssql->read("myTable"))==0);

	$ssql->put("myTable", ['name' => "Foo", 'id' => 1]);
	$r = $ssql->read("myTable");
	assert(count($r)==1);
	assert($r[0]->id==1);
	assert($r[0]->name=="Foo");
	assert($r[0]->text==NULL);
	assert($r->id==1);
	assert($r->name=="Foo");
	assert($r->text==NULL);

	$ssql->put("myTable", ['text' => "Bar"], ['id' => 1]);
	$r = $ssql->read("myTable");
	assert(count($r)==1);
	assert($r[0]->id==1);
	assert($r[0]->name=="Foo");
	assert($r[0]->text=="Bar");
	assert($r->id==1);
	assert($r->name=="Foo");
	assert($r->text=="Bar");

	$ssql->renameColumn("myTable", "text", "text1");
	$ssql->addColumn("myTable", "text2", ['type' => "VARCHAR", 'size' => 512, 'NULL' => true]);
	$r = $ssql->read("myTable");
	assert(count($r)==1);
	$cols = $r->getColumns();
	assert(count($cols)==4);
	assert($cols[0]->name=="id");
	assert($cols[0]->type=="INT");
	assert($cols[0]->size==NULL);
	assert($cols[1]->name=="name");
	assert($cols[1]->type=="VARCHAR");
	assert($cols[1]->size==16);
	assert($cols[2]->name=="text1");
	assert($cols[2]->type=="VARCHAR");
	assert($cols[2]->size==256);
	assert($cols[3]->name=="text2");
	assert($cols[3]->type=="VARCHAR");
	assert($cols[3]->size==512);
	assert($r[0]->id==1);
	assert($r[0]->name=="Foo");
	assert($r[0]->text1=="Bar");
	assert($r[0]->text2==NULL);
	assert($r->id==1);
	assert($r->name=="Foo");
	assert($r->text1=="Bar");
	assert($r->text2==NULL);

	$ssql->removeColumn("myTable", "text2");
	$r = $ssql->read("myTable");
	assert(count($r)==1);
	$cols = $r->getColumns();
	assert(count($cols)==3);
	assert($cols[0]->name=="id");
	assert($cols[0]->type=="INT");
	assert($cols[0]->size==NULL);
	assert($cols[1]->name=="name");
	assert($cols[1]->type=="VARCHAR");
	assert($cols[1]->size==16);
	assert($cols[2]->name=="text1");
	assert($cols[2]->type=="VARCHAR");
	assert($cols[2]->size==256);
	assert($r[0]->id==1);
	assert($r[0]->name=="Foo");
	assert($r[0]->text1=="Bar");
	assert($r->id==1);
	assert($r->name=="Foo");
	assert($r->text1=="Bar");

	$ssql->addColumn("myTable", "text2", ['type' => "VARCHAR", 'size' => 128, 'default' => "Baz"]);
	$r = $ssql->read("myTable");
	assert(count($r)==1);
	$cols = $r->getColumns();
	assert(count($cols)==4);
	assert($cols[0]->name=="id");
	assert($cols[0]->type=="INT");
	assert($cols[0]->size==NULL);
	assert($cols[1]->name=="name");
	assert($cols[1]->type=="VARCHAR");
	assert($cols[1]->size==16);
	assert($cols[2]->name=="text1");
	assert($cols[2]->type=="VARCHAR");
	assert($cols[2]->size==256);
	assert($cols[3]->name=="text2");
	assert($cols[3]->type=="VARCHAR");
	assert($cols[3]->size==128);
	assert($r[0]->id==1);
	assert($r[0]->name=="Foo");
	assert($r[0]->text1=="Bar");
	assert($r[0]->text2=="Baz");
	assert($r->id==1);
	assert($r->name=="Foo");
	assert($r->text1=="Bar");
	assert($r->text2=="Baz");

	$ssql->beginTransaction();
	$ssql->renameTable("myTable", "table");
	$tables = $ssql->tableList();
	assert(count($tables)==1 && $tables[0]=="table");
	$ssql->rollBackTransaction();
	$tables = $ssql->tableList();
	assert(count($tables)==1 && $tables[0]=="myTable");

	$ssql->beginTransaction();
	$ssql->put("myTable", [2, "ABC", "AAA", "BBB"]);
	$ssql->put("myTable", [3, "XYZ", "XXX", "YYY"]);
	$ssql->endTransaction();
	$r = $ssql->read("myTable");
	assert(count($r)==3);
	assert($r[0]->id==1);
	assert($r[0]->name=="Foo");
	assert($r[0]->text1=="Bar");
	assert($r[0]->text2=="Baz");
	assert($r[1]->id==2);
	assert($r[1]->name=="ABC");
	assert($r[1]->text1=="AAA");
	assert($r[1]->text2=="BBB");
	assert($r[2]->id==3);
	assert($r[2]->name=="XYZ");
	assert($r[2]->text1=="XXX");
	assert($r[2]->text2=="YYY");

	$r = $ssql->read("myTable", ['id' => 1]);
	assert(count($r)==1);
	assert($r->id==1);
	assert($r->name=="Foo");
	assert($r->text1=="Bar");
	assert($r->text2=="Baz");

	$r = $ssql->read("myTable", ['id' => 2]);
	assert(count($r)==1);
	assert($r->id==2);
	assert($r->name=="ABC");
	assert($r->text1=="AAA");
	assert($r->text2=="BBB");

	$r = $ssql->read("myTable", ['id' => 3]);
	assert(count($r)==1);
	assert($r->id==3);
	assert($r->name=="XYZ");
	assert($r->text1=="XXX");
	assert($r->text2=="YYY");

	$r = $ssql->read("myTable", ['id' => [1, 3]]);
	assert(count($r)==2);
	assert($r[0]->name=="Foo");
	assert($r[0]->text1=="Bar");
	assert($r[0]->text2=="Baz");
	assert($r[1]->name=="XYZ");
	assert($r[1]->text1=="XXX");
	assert($r[1]->text2=="YYY");

	$r = $ssql->read("myTable", ['id' => [1, 3], 'name' => ["ABC", "XYZ"]]);
	assert(count($r)==1);
	assert($r->id==3);
	assert($r->name=="XYZ");
	assert($r->text1=="XXX");
	assert($r->text2=="YYY");

	$r = $ssql->read("myTable", ['id' => [1, 2], 'name' => "XYZ"]);
	assert(count($r)==0);

	$r = $ssql->read("myTable", ['id' => [2], 'name' => ["XYZ"]]);
	assert(count($r)==0);

	$r = $ssql->read("myTable", ['id' => 2, 'name' => "XYZ"]);
	assert(count($r)==0);

	$r = $ssql->read("myTable", ['id' => 2, 'name' => "XYZ"], SQ::COND_OR);
	assert(count($r)==2);
	assert($r[0]->name=="ABC");
	assert($r[0]->text1=="AAA");
	assert($r[0]->text2=="BBB");
	assert($r[1]->name=="XYZ");
	assert($r[1]->text1=="XXX");
	assert($r[1]->text2=="YYY");

	$ssql->put("myTable", NULL, ['name' => "Foo"]);
	$r = $ssql->read("myTable");
	assert(count($r)==2);
	assert($r[0]->name=="ABC");
	assert($r[0]->text1=="AAA");
	assert($r[0]->text2=="BBB");
	assert($r[1]->name=="XYZ");
	assert($r[1]->text1=="XXX");
	assert($r[1]->text2=="YYY");

	$ssql->put("myTable", NULL);
	$r = $ssql->read("myTable");
	assert(count($r)==0);

	$ssql->deleteTable("myTable");
	$tables = $ssql->tableList();
	assert(count($tables)==0);

	$ssql->createTable("table1", [
		'table1_id' => [
			'type' => "INTEGER",
			'other' => "PRIMARY KEY AUTOINCREMENT"
		],
		'table1_text' => [
			'type' => "CHAR",
			'size' => 8,
			'other' => "UNIQUE"
		],
		'table1_bool' => [
			'type' => "BOOL",
			'default' => true
		]
	]);
	$ssql->createTable("table2", [
		'table2_id' => [
			'type' => "INTEGER",
			'other' => "PRIMARY KEY AUTOINCREMENT"
		],
		'table2_ref' => [
			'type' => "INTEGER",
			'other' => "REFERENCES `table1` (`table1_id`)"
		],
		'table2_num' => [
			'type' => "BIGINT"
		]
	]);
	$tables = $ssql->tableList();
	assert(count($tables)==3);
	assert($tables[0]=="sqlite_sequence");
	array_shift($tables);
	assert(count($tables)==2);
	assert($tables[0]=="table1");
	assert($tables[1]=="table2");

	$r = $ssql->read("table1");
	assert(count($r)==0);
	$cols = $r->getColumns();
	assert(count($cols)==3);
	assert($cols[0]->name=="table1_id");
	assert($cols[0]->type=="INT");
	assert($cols[0]->size==NULL);
	assert($cols[1]->name=="table1_text");
	assert($cols[1]->type=="CHAR");
	assert($cols[1]->size==8);
	assert($cols[2]->name=="table1_bool");
	assert($cols[2]->type=="BOOL");
	assert($cols[2]->size==NULL);
	$r = $ssql->read("table2");
	assert(count($r)==0);
	$cols = $r->getColumns();
	assert(count($cols)==3);
	assert($cols[0]->name=="table2_id");
	assert($cols[0]->type=="INT");
	assert($cols[0]->size==NULL);
	assert($cols[1]->name=="table2_ref");
	assert($cols[1]->type=="INT");
	assert($cols[1]->size==NULL);
	assert($cols[2]->name=="table2_num");
	assert($cols[2]->type=="BIGINT");
	assert($cols[2]->size==NULL);

	$ssql->put("table1", ['table1_text' => "abcdefgh"]);
	$r = $ssql->read("table1");
	assert(count($r)==1);
	assert($r->table1_id==1);
	assert($r->table1_text=="abcdefgh");
	assert($r->table1_bool==true);

	$ssql->put("table2", ['table2_ref' => 1, 'table2_num' => 22]);
	$ssql->put("table2", ['table2_ref' => 1, 'table2_num' => 42]);
	$r = $ssql->read("table2");
	assert(count($r)==2);
	assert($r[0]->table2_id==1);
	assert($r[0]->table2_ref==1);
	assert($r[0]->table2_num==22);
	assert($r[1]->table2_id==2);
	assert($r[1]->table2_ref==1);
	assert($r[1]->table2_num==42);
	$r = $ssql->get("table1", ['join' => "table2", 'on' => ['table2_ref' => "table1_id"]]);
	assert(count($r)==2);
	assert($r[0]->table1_id==1);
	assert($r[0]->table1_text=="abcdefgh");
	assert($r[0]->table1_bool==true);
	assert($r[0]->table2_id==1);
	assert($r[0]->table2_ref==1);
	assert($r[0]->table2_num==22);
	assert($r[1]->table1_id==1);
	assert($r[1]->table1_text=="abcdefgh");
	assert($r[1]->table1_bool==true);
	assert($r[1]->table2_id==2);
	assert($r[1]->table2_ref==1);
	assert($r[1]->table2_num==42);
	$ssql->put("table1", ['table1_text' => "ssssaggg", 'table1_bool' => false]);
	$r = $ssql->read("table1");
	assert(count($r)==2);
	assert($r[0]->table1_id==1);
	assert($r[0]->table1_text=="abcdefgh");
	assert($r[0]->table1_bool==true);
	assert($r[1]->table1_id==2);
	assert($r[1]->table1_text=="ssssaggg");
	assert($r[1]->table1_bool==false);
	$r = $ssql->get("table1", ['join' => "table2", 'on' => ['table2_ref' => "table1_id"]]);
	assert(count($r)==2);
	assert($r[0]->table1_id==1);
	assert($r[0]->table1_text=="abcdefgh");
	assert($r[0]->table1_bool==true);
	assert($r[0]->table2_id==1);
	assert($r[0]->table2_ref==1);
	assert($r[0]->table2_num==22);
	assert($r[1]->table1_id==1);
	assert($r[1]->table1_text=="abcdefgh");
	assert($r[1]->table1_bool==true);
	assert($r[1]->table2_id==2);
	assert($r[1]->table2_ref==1);
	assert($r[1]->table2_num==42);
	$r = $ssql->get("table1", ['join' => "table2", 'on' => ['table2_ref' => "table1_id"]], SQ::JOIN_FULL);
	assert(count($r)==3);
	assert($r[0]->table1_id==1);
	assert($r[0]->table1_text=="abcdefgh");
	assert($r[0]->table1_bool==true);
	assert($r[0]->table2_id==1);
	assert($r[0]->table2_ref==1);
	assert($r[0]->table2_num==22);
	assert($r[1]->table1_id==1);
	assert($r[1]->table1_text=="abcdefgh");
	assert($r[1]->table1_bool==true);
	assert($r[1]->table2_id==2);
	assert($r[1]->table2_ref==1);
	assert($r[1]->table2_num==42);
	assert($r[2]->table1_id==2);
	assert($r[2]->table1_text=="ssssaggg");
	assert($r[2]->table1_bool==false);
	assert($r[2]->table2_id==NULL);
	assert($r[2]->table2_ref==NULL);
	assert($r[2]->table2_num==NULL);
	$r = $ssql->get("table1", ['join' => "table2", 'on' => ['table2_ref' => "table1_id"]], SQ::JOIN_LEFT);
	assert(count($r)==3);
	assert($r[0]->table1_id==1);
	assert($r[0]->table1_text=="abcdefgh");
	assert($r[0]->table1_bool==true);
	assert($r[0]->table2_id==1);
	assert($r[0]->table2_ref==1);
	assert($r[0]->table2_num==22);
	assert($r[1]->table1_id==1);
	assert($r[1]->table1_text=="abcdefgh");
	assert($r[1]->table1_bool==true);
	assert($r[1]->table2_id==2);
	assert($r[1]->table2_ref==1);
	assert($r[1]->table2_num==42);
	assert($r[2]->table1_id==2);
	assert($r[2]->table1_text=="ssssaggg");
	assert($r[2]->table1_bool==false);
	assert($r[2]->table2_id==NULL);
	assert($r[2]->table2_ref==NULL);
	assert($r[2]->table2_num==NULL);
	$r = $ssql->get("table1", ['join' => "table2", 'on' => ['table2_ref' => "table1_id"]], SQ::JOIN_RIGHT);
	assert(count($r)==2);
	assert($r[0]->table1_id==1);
	assert($r[0]->table1_text=="abcdefgh");
	assert($r[0]->table1_bool==true);
	assert($r[0]->table2_id==1);
	assert($r[0]->table2_ref==1);
	assert($r[0]->table2_num==22);
	assert($r[1]->table1_id==1);
	assert($r[1]->table1_text=="abcdefgh");
	assert($r[1]->table1_bool==true);
	assert($r[1]->table2_id==2);
	assert($r[1]->table2_ref==1);
	assert($r[1]->table2_num==42);

	$ssql->put("table1", [NULL, "", true]);
	$r = $ssql->read("table1");
	assert(count($r)==3);
	assert($r[0]->table1_id==1);
	assert($r[0]->table1_text=="abcdefgh");
	assert($r[0]->table1_bool==true);
	assert($r[1]->table1_id==2);
	assert($r[1]->table1_text=="ssssaggg");
	assert($r[1]->table1_bool==false);
	assert($r[2]->table1_id==3);
	assert($r[2]->table1_text=="");
	assert($r[2]->table1_bool==true);

	$ssql->createTable("table3", [
		'table3_id' => [
			'type' => "INTEGER",
			'other' => "PRIMARY KEY AUTOINCREMENT"
		],
		'table3_ref' => [
			'type' => "INTEGER",
			'other' => "UNIQUE REFERENCES `table1` (`table1_id`)",
			'NULL' => true
		],
		'table3_num' => [
			'type' => "TINYINT"
		]
	]);
	$tables = $ssql->tableList();
	assert(count($tables)==4);
	assert($tables[0]=="sqlite_sequence");
	array_shift($tables);
	assert(count($tables)==3);
	assert($tables[0]=="table1");
	assert($tables[1]=="table2");
	assert($tables[2]=="table3");
	$r = $ssql->read("table3");
	assert(count($r)==0);
	$cols = $r->getColumns();
	assert(count($cols)==3);
	assert($cols[0]->name=="table3_id");
	assert($cols[0]->type=="INT");
	assert($cols[0]->size==NULL);
	assert($cols[1]->name=="table3_ref");
	assert($cols[1]->type=="INT");
	assert($cols[1]->size==NULL);
	assert($cols[2]->name=="table3_num");
	assert($cols[2]->type=="TINYINT");
	assert($cols[2]->size==NULL);

	$ssql->put("table3", [NULL, NULL, 16]);
	$ssql->put("table3", [NULL, 1, 22]);
	$ssql->put("table3", [NULL, 2, 48]);
	$ssql->put("table3", [NULL, 3, 32]);
	$r = $ssql->read("table3");
	assert(count($r)==4);
	assert($r[0]->table3_id==1);
	assert($r[0]->table3_ref==NULL);
	assert($r[0]->table3_num==16);
	assert($r[1]->table3_id==2);
	assert($r[1]->table3_ref==1);
	assert($r[1]->table3_num==22);
	assert($r[2]->table3_id==3);
	assert($r[2]->table3_ref==2);
	assert($r[2]->table3_num==48);
	assert($r[3]->table3_id==4);
	assert($r[3]->table3_ref==3);
	assert($r[3]->table3_num==32);
	$r = $ssql->get("table3", ['join' => "table1", 'on' => ['table3_ref' => "table1_id"], 'cols' => ['id' => "table3_id", 'num' => "table3_num"], 'order' => "table3_num"]);
	assert(count($r)==3);
	assert($r[0]->id==2);
	assert($r[0]->num==22);
	assert($r[1]->id==4);
	assert($r[1]->num==32);
	assert($r[2]->id==3);
	assert($r[2]->num==48);
	$r = $ssql->get("table3", ['cols' => ['num' => "table3_num", 'half' => function($r) { return ($r->num / 2); }], 'order' => "num", 'cond' => $ssql->cond()->gt("num", 20)], SQ::ORDER_DESC);
	assert(count($r)==3);
	assert($r[0]->num==48);
	assert($r[0]->half==24);
	assert($r[1]->num==32);
	assert($r[1]->half==16);
	assert($r[2]->num==22);
	assert($r[2]->half==11);
	$r = $ssql->get("table3", ['cols' => ['num' => "table3_num"], 'cond' => $ssql->cond()->gte("num", 20)->lte("num", 40)->not($ssql->cond()->eq("num", 32))]);
	assert(count($r)==1);
	assert($r[0]->num==22);
	$r = $ssql->get("table3", ['cols' => ['num' => "table3_num"], 'cond' => $ssql->cond()->lt("num", 20)->gt("num", 40, SQ::COND_OR), 'order' => "num"]);
	assert(count($r)==2);
	assert($r[0]->num==16);
	assert($r[1]->num==48);
	$r = $ssql->get("table3", ['cols' => ['num' => "table3_num"], 'cond' => $ssql->cond()->between("num", 20, 40)->not(), 'order' => "num"]);
	assert(count($r)==2);
	assert($r[0]->num==16);
	assert($r[1]->num==48);

	$ssql->q("UPDATE `table3` SET `table3_num` = '%1' WHERE `table3_id` = '%0'", "3", "42");
	$r = $ssql->read("table3");
	assert(count($r)==4);
	assert($r[0]->table3_id==1);
	assert($r[0]->table3_ref==NULL);
	assert($r[0]->table3_num==16);
	assert($r[1]->table3_id==2);
	assert($r[1]->table3_ref==1);
	assert($r[1]->table3_num==22);
	assert($r[2]->table3_id==3);
	assert($r[2]->table3_ref==2);
	assert($r[2]->table3_num==42);
	assert($r[3]->table3_id==4);
	assert($r[3]->table3_ref==3);
	assert($r[3]->table3_num==32);
	$r = $ssql->get("table3", ['cols' => ['num' => "table3_num"], 'order' => "table3_num", 'limit' => 1]);
	assert(count($r)==1);
	assert($r[0]->num==16);

	$ssql->inc = "UPDATE `table3` SET `table3_num` = `table3_num` + 1 WHERE `table3_id` = '%0'";
	$ssql->inc(4);
	$r = $ssql->read("table3");
	assert(count($r)==4);
	assert($r[0]->table3_id==1);
	assert($r[0]->table3_ref==NULL);
	assert($r[0]->table3_num==16);
	assert($r[1]->table3_id==2);
	assert($r[1]->table3_ref==1);
	assert($r[1]->table3_num==22);
	assert($r[2]->table3_id==3);
	assert($r[2]->table3_ref==2);
	assert($r[2]->table3_num==42);
	assert($r[3]->table3_id==4);
	assert($r[3]->table3_ref==3);
	assert($r[3]->table3_num==33);
	$r = $ssql->get("table1", [
		'join' => "table3",
		'on' => ['table3_ref' => "table1_id"],
		'cols' => ['text' => "table1_text", 'num' => "table3_num"],
		'cond' => $ssql->cond()->eq("num", [33])->contains("text", ["bcd"], SQ::COND_OR),
		'order' => "num"
	], SQ::JOIN_RIGHT | SQ::ORDER_DESC);
	assert(count($r)==2);
	assert($r[0]->text==NULL);
	assert($r[0]->num==33);
	assert($r[1]->text=="abcdefgh");
	assert($r[1]->num==22);

	$ssql->changeDB("./test2.sqlite3");
	$tables = $ssql->tableList();
	assert(count($tables)==0);

	$ssql->createTable("table", ['text' => ['type' => "VARCHAR", 'size' => 8]]);
	$tables = $ssql->tableList();
	assert(count($tables)==1);
	assert($tables[0]=="table");
	$r = $ssql->read("table");
	assert(count($r)==0);
	$cols = $r->getColumns();
	assert(count($cols)==1);
	assert($cols[0]->name=="text");
	assert($cols[0]->type=="VARCHAR");
	assert($cols[0]->size==8);

	$ssql->put("table", ["ab"]);
	$r = $ssql->read("table");
	$cols = $r->getColumns();
	assert(count($cols)==1);
	assert($cols[0]->name=="text");
	assert($cols[0]->type=="VARCHAR");
	assert($cols[0]->size==8);
	assert(count($r)==1);
	assert($r[0]->text=="ab");

	$ssql->put("table", ["cd"]);
	$r = $ssql->read("table");
	$cols = $r->getColumns();
	assert(count($cols)==1);
	assert($cols[0]->name=="text");
	assert($cols[0]->type=="VARCHAR");
	assert($cols[0]->size==8);
	assert(count($r)==2);
	assert($r[0]->text=="ab");
	assert($r[1]->text=="cd");

	$ssql->put("table", ["ad"]);
	$r = $ssql->read("table");
	$cols = $r->getColumns();
	assert(count($cols)==1);
	assert($cols[0]->name=="text");
	assert($cols[0]->type=="VARCHAR");
	assert($cols[0]->size==8);
	assert(count($r)==3);
	assert($r[0]->text=="ab");
	assert($r[1]->text=="cd");
	assert($r[2]->text=="ad");
	$r = $ssql->read("table", $ssql->cond()->in("text", [["ab"], ["cd"]]));
	assert(count($r)==2);
	assert($r[0]->text=="ab");
	assert($r[1]->text=="cd");
	$r = $ssql->read("table", $ssql->cond()->begins("text", ["a"]));
	assert(count($r)==2);
	assert($r[0]->text=="ab");
	assert($r[1]->text=="ad");
	$r = $ssql->read("table", $ssql->cond()->ends("text", ["d"]));
	assert(count($r)==2);
	assert($r[0]->text=="cd");
	assert($r[1]->text=="ad");

	$ssql->addColumn("table", "otherText", ['type' => "VARCHAR", 'size' => 2, 'default' => "ab"]);
	$r = $ssql->read("table");
	$cols = $r->getColumns();
	assert(count($cols)==2);
	assert($cols[0]->name=="text");
	assert($cols[0]->type=="VARCHAR");
	assert($cols[0]->size==8);
	assert($cols[1]->name=="otherText");
	assert($cols[1]->type=="VARCHAR");
	assert($cols[1]->size==2);
	assert(count($r)==3);
	assert($r[0]->text=="ab");
	assert($r[0]->otherText=="ab");
	assert($r[1]->text=="cd");
	assert($r[1]->otherText=="ab");
	assert($r[2]->text=="ad");
	assert($r[2]->otherText=="ab");
	$r = $ssql->read("table", $ssql->cond()->in("text", [["ad"], "otherText"]));
	assert(count($r)==2);
	assert($r[0]->text=="ab");
	assert($r[0]->otherText=="ab");
	assert($r[1]->text=="ad");
	assert($r[1]->otherText=="ab");
	$r = $ssql->read("table", $ssql->cond()->in("text", [["ad", "cd"], "otherText"]));
	assert(count($r)==3);
	assert($r[0]->text=="ab");
	assert($r[0]->otherText=="ab");
	assert($r[1]->text=="cd");
	assert($r[1]->otherText=="ab");
	assert($r[2]->text=="ad");
	assert($r[2]->otherText=="ab");

	$ssql->changeDB("./test.sqlite3");
	$tables = $ssql->tableList();
	assert(count($tables)==4);
	assert($tables[0]=="sqlite_sequence");
	array_shift($tables);
	assert(count($tables)==3);
	assert($tables[0]=="table1");
	assert($tables[1]=="table2");
	assert($tables[2]=="table3");

	class MySSQL extends SSQL {
		protected $host = "./test2.sqlite3";
		public $debug = true;
	};
	$ssql = new MySSQL();
	$tables = $ssql->tableList();
	$tables = $ssql->tableList();
	assert(count($tables)==1);
	assert($tables[0]=="table");
	assert($ssql->exists("table", ['text' => "ab"]));
	assert($ssql->exists("table", ['text' => "ab", 'otherText' => "ab"]));
	assert(!$ssql->exists("table", ['text' => "cd", 'otherText' => "cd"]));
	assert($ssql->exists("table", ['text' => "cd", 'otherText' => "cd"], SQ::COND_OR));

	unlink("./test.sqlite3");
	unlink("./test2.sqlite3");
?>
