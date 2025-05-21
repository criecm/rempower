<?php
namespace Dsiecm\Rempower\Utils;
class Str {
	public function stripSNMPvalue($str) {
		$motifs=array('/^(INTEGER|STRING): /','/^"/','/"$/');
		if (preg_match('/INTEGER/',$str)) {
			$motifs[]='/[^0-9]/';
		}
		return preg_replace($motifs,'',$str);
	}
}

