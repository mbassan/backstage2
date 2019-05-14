<?php 

$CFG->form_legend = 'Import Fiat Deposits';
$upload = new Form('deposits',false,false,'form1');
$upload->verify();

if (is_array($CFG->temp_files)) {
	$key = key($CFG->temp_files);
	$transactions = 0;
	$cancelled = 0;
	
	$currencies = array();
	$sql = 'SELECT * FROM currencies';
	$result = db_query_array($sql);
	foreach ($result as $row) {
		$currencies[$row['currency']] = $row;
		$currencies[$row['id']] = $row;
	}
	
	// CSV -> each row should be:  bank_id_number (any unique number), user_number, amount, currency (Ex. USD or EUR), bank_account_number (optional)
	
	if (($handle = fopen($CFG->dirroot.$CFG->temp_file_location.$CFG->temp_files[$key], "r")) !== FALSE) {
	    db_start_transaction();
		while (($data = fgetcsv($handle, 1000, ";",'"')) !== FALSE) {
			if (!($data[0] > 0))
				continue;
			
			if (substr_count($data[0],',') > 1) {
				$data = explode(',',$data[0]);
			}
			
			$sql = 'SELECT id FROM requests WHERE crypto_id = '.$data[0];
			$result = db_query_array($sql);
			if ($result)
				continue;
			
			if (!empty($currencies[strtoupper($data[3])]))
				$currency_info = $currencies[strtoupper($data[3])];
			else
				continue;
			
			$sql = 'SELECT site_users_balances.id AS balance_id, site_users.id AS site_user, site_users_balances.balance AS cur_balance, site_users.notify_deposit_bank AS notify_deposit_bank, site_users.first_name AS first_name, site_users.last_name AS last_name, site_users.email AS email, site_users.last_lang AS last_lang FROM site_users LEFT JOIN site_users_balances ON (site_users_balances.site_user = site_users.id AND site_users_balances.currency = '.$currency_info['id'].') WHERE site_users.user = '.$data[1].' FOR UPDATE';
			$result = db_query_array($sql);

			if ($result) {
				$balance_record = false;
				if ($result[0]['balance_id'] > 0)
					$balance_record = DB::getRecord('site_users_balances',$result[0]['balance_id'],0,1);
				
				if ($balance_record)
					db_update('site_users_balances',$result[0]['balance_id'],array('balance'=>(number_format($result[0]['cur_balance'] + $data[2]))));
				else {
					$balance_id = db_insert('site_users_balances',array('balance'=>(number_format($data[2])),'site_user'=>$result[0]['site_user'],'currency'=>$currency_info['id']));
					$balance_record = DB::getRecord('site_users_balances',$balance_id,0,1);
					$result[0]['balance_id'] = $balance_record['id'];
					$result[0]['cur_balance'] = 0;
				}	
					
				$insert_id = db_insert('requests',array('date'=>date('Y-m-d H:i:s'),'site_user'=>$result[0]['site_user'],'currency'=>$currency_info['id'],'amount'=>number_format($data[2],2,'.',''),'description'=>$CFG->deposit_fiat_desc,'request_type'=>$CFG->request_deposit_id,'request_status'=>$CFG->request_completed_id,'account'=>$data[4],'crypto_id'=>$data[0]));
				db_insert('history',array('date'=>date('Y-m-d H:i:s'),'history_action'=>4,'site_user'=>$result[0]['site_user'],'request_id'=>$insert_id,'balance_before'=>$result[0]['cur_balance'],'balance_after'=>($result[0]['cur_balance'] + $data[2])));
				
				if ($result[0]['notify_deposit_bank'] == 'Y') {
				    $result[0]['amount'] = number_format($data[2],2,'.','');
				    $result[0]['id'] = $insert_id;
				    $CFG->language = ($result[0]['last_lang']) ? $result[0]['last_lang'] : 'en';

				    $email = SiteEmail::getRecord('new-deposit');
				    Email::send($CFG->form_email,$result[0]['email'],str_replace('[amount]',number_format($data[2],2),str_replace('[currency]',$currency_info['currency'],$email['title'])),$CFG->form_email_from,false,$email['content'],$result[0]);
				}
				
				$transactions++;
			}
			else {
				$insert_id = db_insert('requests',array('date'=>date('Y-m-d H:i:s'),'site_user'=>$result[0]['site_user'],'currency'=>$currency_info['id'],'amount'=>number_format($data[2],2,'.',''),'description'=>$CFG->deposit_fiat_desc,'request_type'=>$CFG->request_deposit_id,'request_status'=>$CFG->request_cancelled_id,'account'=>$data[4],'crypto_id'=>$data[0]));
				$cancelled++;
			}
		}
		db_commit();
		fclose($handle);
		
		if ($transactions > 0)
			$upload->messages[] = $transactions.' new transactions were credited.';
		if ($cancelled > 0)
			$upload->errors[] = $cancelled.' transactions could not be credited because of an information mismatch.';
	}
	
	unlink($CFG->dirroot.$CFG->temp_file_location.$CFG->temp_files[$key]);
	unset($CFG->temp_files);
}

$upload->show_errors();
$upload->show_messages();
$upload->fileInput('deposits','Deposits Export File',1,array('csv'),false,false,false,1,false,false,false,false,false,1);
$upload->submitButton('Upload','Upload');
$upload->display();



/*
$CFG->form_legend = 'Export Fiat Withdrawals';
$download = new Form('withadrawals',false,false,'form1');
$download->verify();

if ($_REQUEST['withadrawals'] && !is_array($download->errors)) {
	if ($download->info['currency'] > 0) {
		$currency_info = DB::getRecord('currencies',$download->info['currency'],0,1,false,false,false,1);
		if (!$currency_info) {
			$download->errors[] = 'Invalid currency.';
		}
		else {
			$sql = "SELECT * FROM requests WHERE currency = {$download->info['currency']} AND request_status = {$CFG->request_pending_id} AND request_type = {$CFG->request_withdrawal_id}";
			$result = db_query_array($sql);
			if ($result) {
				$_SESSION['export_withdrawals'] = false;
				
				foreach ($result as $row) {
					$sql = "SELECT id FROM bank_accounts WHERE account_number = {$row['account']} AND site_user = {$row['site_user']}";
					$result1 = db_query_array($sql);

					if (!$result) {
						$download->errors[] = 'Account mismatch for request id '.$row['id'];
						db_update('requests',$row['id'],array('request_status'=>$CFG->request_cancelled_id));
						continue;
					}
					
					$withdrawals[$row['account']]['escrow_account'] = $currency_info['account_number'];
					$withdrawals[$row['account']]['amount'][] = $row['amount'];
					$withdrawals[$row['account']]['id'][] = $row['id'];
					$withdrawals[$row['account']]['date'][] = date('Y-m-d',strtotime($row['date']));
 				}

				if ($withdrawals) {
					foreach ($withdrawals as $account_num => $row) {
						$narrative = array();
						foreach ($row['id'] as $i => $val) {
							$narrative[] = '#'.$val.' @'.$row['date'][$i];
						}
						$_SESSION['export_withdrawals'][] = array($row['escrow_account'],$account_num,array_sum($row['amount']),$CFG->exchange_name.': '.implode(', ',$narrative));
					}
				}
				
				if ($_SESSION['export_withdrawals']) {
					echo '<iframe src="custom/withdrawals_download.php?currency='.$currency_info['currency'].'" style="height:0;width:0;border:none;"></iframe>';
				}
			}
		}
	}
}

$download->show_errors();
$download->show_messages();
$download->selectInput('currency','Currency',1,false,false,'currencies',array('currency'));
$download->submitButton('Download','Download Withdrawals CSV');
$download->display();



$CFG->form_legend = 'Account For Widtdrawals From Escrows';
$withdraw = new Form('withdraw',false,false,'form1');
$withdraw->verify();

if ($_REQUEST['withdraw'] && !is_array($withdraw->errors)) {
	if ($withdraw->info['currency'] > 0 && $withdraw->info['amount'] > 0) {
		db_start_transaction();
		
		$currency_info = DB::getRecord('currencies',$withdraw->info['currency'],0,1,false,false,false,1);
		if (!$currency_info) {
			$withdraw->errors[] = 'Invalid currency.';
		}
		elseif (!($currency_info[strtolower($currency_info['currency']).'_escrow'] - $withdraw->info['amount'] > 0)) {
			$withdraw->errors[] = 'Balance too low to satisfy withdrawal.';
		}
		else {
			$status = DB::getRecord('status',1,0,1,false,false,false,1);
			$sql = 'UPDATE status SET '.strtolower($currency_info['currency']).'_escrow = '.strtolower($currency_info['currency']).'_escrow - '.$withdraw->info['amount'].' WHERE id = 1';
			db_query($sql);
			
			$withdraw->messages[] = $withdraw->info['amount'].' subtracted from '.$currency_info['currency'];
		}
		
		db_commit();
	}
}

$withdraw->show_errors();
$withdraw->show_messages();
$withdraw->selectInput('currency','Currency',1,false,false,'currencies',array('currency'));
$withdraw->textInput('amount','Amount',1);
$withdraw->submitButton('Withdraw','Withdraw');
$withdraw->display();

*/


?>