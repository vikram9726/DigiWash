<?php
require 'config.php';
try { $pdo->exec("ALTER TABLE marketplace_orders ADD COLUMN invoice_no VARCHAR(30) DEFAULT NULL;"); echo "Added invoice_no\n"; } catch(Exception $e) { echo $e->getMessage()."\n"; }
