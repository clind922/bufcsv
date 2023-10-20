<?php
ini_set('date.timezone', 'Europe/Stockholm');
function debug_array($array)
{
	echo '<pre class="error">';
	if (is_array($array))
		print_r($array);
	else
		var_dump($array);
	echo '</pre>';
}
function mb_utf8(string $text, $enc_in, $enc_out): string
{
	return mb_convert_encoding($text, $enc_out, $enc_in);
	if(!$encoding = mb_detect_encoding($text))
		return $text;
	debug_array($encoding);
	$out =  mb_convert_encoding($text, "UTF-8", $encoding);
	debug_array($out);
	return $out;
}
function dd($data)
{
	debug_array($data);
	die;
}
function flip_name($name) {
	if(stristr($name, ','))
		return implode(' ', array_map('trim', array_reverse(explode(',', $name))));
	return $name;
}
function detect_delimiter($csvFile)
{
	$delimiters = [';' => 0, ',' => 0, "\t" => 0, "|" => 0];

	$handle = fopen($csvFile, "r");
	$firstLine = fgets($handle);
	fclose($handle);
	foreach ($delimiters as $delimiter => &$count)
		$count = count(str_getcsv($firstLine, $delimiter));

	return array_search(max($delimiters), $delimiters);
}

if(!empty($_POST))
{
	$phone_pattern = '/^[\d\-\+\s]+$/';
	$enc_in = $_POST['enc_in'] ?? 'UTF-8';
	$enc_out = $_POST['enc_out'] ?? 'UTF-8';
	$separator = $_POST['separator'] ?? NULL;
	$output_formatted = [];
	foreach($_FILES['file']['name'] as $key => $file_name)
	{
		if($_FILES['file']['error'][$key] != '0')
		{
			if(count($_FILES['file']['name']) == 1)
				die('Upload error: ' . $file_name);
			continue;
		}
		$csv_path = $_FILES['file']['tmp_name'][$key];

		if(!$fh = fopen($csv_path, 'r'))
		{
			if(count($_FILES['file']['name']) == 1)
				die ('Could not open file: ' . $csv_path);
			continue;
		}

		if(empty($separator))
			$separator = detect_delimiter($csv_path);

		$i = 0;
		$data = [];
		$all_p_data = [];
		while($csv = fgetcsv($fh, 0, $separator))
			$data[] = $csv;

		// Delete the temp file
		unlink($csv_path);

		$output = [];
		$buffer = [];
		$avd = 1;
		$last_ord = 0;

		foreach($data as $index => $row)
		{
			// Something in the first col,
			if($row[0])
			{
				// Probably a heading row, skip
				if($row[1])
					continue;
				// Detect new avd from first letter (in lastname)
				if($last_ord && ord(substr($row[0], 0, 1)) < $last_ord)
					$avd++;
				$last_ord = ord(substr($row[0], 0, 1));

				$output[] = $buffer;
				$buffer = array_fill(0, 10, '');
				$buffer[0] = mb_utf8($row[0], $enc_in, $enc_out); //BN
				$buffer[1] = mb_utf8($row[2], $enc_in, $enc_out); //VHN 1
				$buffer[2] = mb_utf8($row[4], $enc_in, $enc_out); //VHN 2
				$buffer[9] = 'Avdelning ' . $avd;

			}
			else // Empty 1st column
			{
				// Look for emails
				if($row[2] && !$buffer[3])
				{
					if(stristr($row[2], '@'))
						$buffer[3] = $row[2];
					else
						$buffer[3] = '';
				}

				if($row[4] && !$buffer[4])
				{
					if(stristr($row[4], '@'))
						$buffer[4] = $row[4];
					else
						$buffer[4] = '';
				}

				// Look for phone numbers

				//VHT 1
				if($row[2] && !$buffer[5])
				{
					if(preg_match($phone_pattern, $row[2]))
						$buffer[5] = $row[2];
					else
						$buffer[5] = '';
				}

				// Alt nr
				if($row[3] && !$buffer[6])
				{
					if(preg_match($phone_pattern, $row[3]))
						$buffer[6] = $row[3];
					else
						$buffer[6] = '';
				}

				//VHT 2
				if($row[4] && !$buffer[7])
				{
					if(preg_match($phone_pattern, $row[4]))
						$buffer[7] = $row[4];
					else
						$buffer[7] = '';
				}

				// Alt nr
				if($row[5] && !$buffer[8])
				{
					if(preg_match($phone_pattern, $row[5]))
						$buffer[8] = $row[5];
					else
						$buffer[8] = '';
				}
			}
		}
		// Empty final buffer
		if($buffer)
			$output[] = $buffer;

		foreach($output as $row)
		{
			if(empty($row))
				continue;
			//Regiform	Förskola/enhet	Unik kod	Avdelning	Barnets namn	Pojke/Flicka	Vårdnadshavarens namn 1	Mailadress vårdnadshavare 1	Vårdnadshavarens namn 2	Mailadress vårdnadshavare 2	Tel vårdnadshavare 1	Tel vårdnadshavare 2

			// Use backup tel if empty
			if(!$row[5] && $row[6])
				$row[5] = $row[6];
			if(!$row[7] && $row[8])
				$row[7] = $row[8];

			$new_row = [
				'Kommunal',
				mb_utf8(str_replace(['_', 'CSV'], [' ', ''], basename($file_name, '.csv')), 'UTF-8', $enc_out), //Enhet
				'',
				$row[9], //Avd
				flip_name($row[0]), //B namn
				mb_utf8('Okänt', 'UTF-8', $enc_out), //Kön
				flip_name($row[1]), //VH1 namn
				$row[3], //VH1 email
				flip_name($row[2]), //VH2 namn
				$row[4], //VH2 email
				$row[5], //VH1 tel
				$row[7], //VH2 tel
			];

			// VH1 tel == VH2 tel
			if($new_row[10] == $new_row[11])
			{
				//VH1 tel alt
				if($row[6] && $row[6] != $new_row[11])
					$new_row[10] = $row[6];
				//VH2 tel alt
				elseif($row[8] && $row[8] != $new_row[10])
					$new_row[11] = $row[8];
			}
			$output_formatted[] = $new_row;
		}



		if($_GET['debug'] ?? FALSE)
			debug_array($output_formatted);
	}

	if(!($_GET['debug'] ?? FALSE))
	{
		#header("Content-type: application/csv; charset=utf-8;sep=" . $delimiter)
		#header("Content-Encoding: " . $enc_out);
		header("Content-Type: application/vnd.ms-excel");
		header("Content-Disposition: attachment; filename=" . 'buf_origo_lista_' . str_replace([' ', ':'], ['_', ''], date('Y-m-d H:i:s')) . ".csv");
		header("Pragma: no-cache");
		header("Expires: 0");
		header("HTTP/1.1 200 OK");
		foreach($output_formatted as $row)
			echo implode(',', $row) . PHP_EOL;
		exit;
	}
}
$options =
	'<option value="Windows-1252">Windows-1252</option>' .
	'<option value="ISO-8859-1">ISO-8859-1</option>' .
	'<option value="UTF-8">UTF-8</option>' .
	'<option value="ASCII">ASCII</option>' .
'';
?>
<!DOCTYPE html>
<html lang="sv">
<head>
	<!--<meta charset="utf-8">-->
	<meta http-equiv="Content-Language" content="en"/>
	<META NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW">
	<title><?php echo $title = 'BUF Origo CSV konverteringsverktyg'; ?></title>
</head>
<body>
<h1><?php echo $title; ?></h1>
<?php
	?>
	<form action="" method="POST" enctype="multipart/form-data">
		<p><input type="file" name="file[]" multiple accept="text/csv,.csv"></p>
		<p>
			<label for="enc_in">Kodn. in:</label> <select name="enc_in" id="enc_in"><?php echo str_replace('"UTF-8"', '"UTF-8" selected', $options); ?></select> /
			<label for="enc_out">Kodn. ut:</label> <select name="enc_out" id="enc_out"><?php echo $options; ?></select>
			<label for="separator">CSV-separator:</label>
			<select name="separator" id="separator">
				<option value="">Auto</option>
				<option value=",">KOMMA (,)</option>
				<option value=";">SEMI-KOLON (;)</option>
				<option value="\t">TAB (\t)</option>
			</select>
		</p>
		<input type="hidden" name="confirm" value="1" />
		<input type="submit" />
	</form>
<?php

?>
</body>
</html>