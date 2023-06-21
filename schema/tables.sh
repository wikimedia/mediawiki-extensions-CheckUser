#!/bin/bash
set -e
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
ENGINES='mysql sqlite postgres'
TABLES='cu_useragent_clienthints_map cu_useragent_clienthints'

cd ../../

for engine in $ENGINES; do
	for table in $TABLES; do
		php maintenance/run.php generateSchemaSql --json "$DIR/$table.json" --type $engine --sql "$DIR/$engine/$table.sql"
	done
done

cd "$DIR"
