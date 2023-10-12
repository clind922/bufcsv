<?php
function debug_array($array)
{
	echo '<pre class="error">';
	if (is_array($array))
		print_r($array);
	else
		var_dump($array);
	echo '</pre>';
}

/**
 * Quick debug
 */

function dd($data)
{
	debug_array($data);
	die;
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta http-equiv="Content-Language" content="en"/>
	<META NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>BUF origo csv converter</title>
</head>
<body>
<h1>BUF origo csv converter</h1>
<?php
	?>
	<form action="" method="POST" enctype="multipart/form-data">
		<input type="file" name="file[]" multiple>
		<input type="hidden" name="confirm" value="1" />
		<input type="submit" />
	</form>
<?php
if(!empty($_POST))
{
	echo '<hr>';
	$phone_pattern = '/[\d-+\s]+/';
	debug_array($_FILES);
	foreach($_FILES['file']['name'] as $key => $name)
	{
		if($_FILES['file']['error'][$key] != '0')
		{
			echo 'Upload error: ' . $name;
			continue;
		}
		$csv_path = $_FILES['file']['tmp_name'][$key];
		debug_array($csv_path);

		if(!$fh = fopen($csv_path, 'r'))
		{
			echo ('Could not open file: ' . $csv_path);
			continue;
		}

		$i = 0;
		$data = [];
		$all_p_data = [];
		while($csv = fgetcsv($fh, 0, ","))
			$data[] = $csv;

		unlink($csv_path);

		debug_array($data);

		$output = [];
		$buffer = [];

		foreach($data as $index => $row)
		{
			// Something in the first col,
			if($row[0])
			{
				// Probably a heading row, skip
				if($row[1])
					continue;
				$output[] = $buffer;
				$buffer = [$row[0]];
			}
			else
			{
				// Look for emails
				if(stristr($row[3], '@'))
					$buffer[] = $row[3];
				else
					$buffer[] = '';

				if(stristr($row[4], '@'))
					$buffer[] = $row[4];
				else
					$buffer[] = '';

				// Look for phone numbers
				if(preg_match($phone_pattern, $row[5]))
					$buffer[] = $row[5];
				else
					$buffer[] = '';

				if(preg_match($phone_pattern, $row[6]))
					$buffer[] = $row[6];
				else
					$buffer[] = '';
			}
		}
		if($buffer)
			$output[] = $buffer;

		debug_array($output);

		#header("Content-Encoding: UTF-8");
		#header("Content-type: application/csv; charset=utf-8;sep=" . $delimiter)

		/*
		header("Content-Type: application/vnd.ms-excel");
		header("Content-Disposition: attachment; filename=" . $name . '_' . str_replace([' ', ':'], ['_', ''], date('Y-m-d H:i:s')) . ".csv");
		header("Pragma: no-cache");
		header("Expires: 0");
		header("HTTP/1.1 200 OK");
		#html_entity_decode
		foreach($output as $row)
			echo implode("\t", $row) . PHP_EOL;*/

		echo '<hr>';
	}
}
?>
</body>
</html>