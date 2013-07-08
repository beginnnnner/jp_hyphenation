<?php
/**
 * マルチバイト文字列を禁則処理を加えて指定文字数で区切り配列として返す。
 * @param string  $str      分割対象のマルチバイト文字列
 * @param integer $width    分割する文字数
 * @param string  $encoding エンコーディングを指定、省略時はphpデフォルト
 * @return array  指定文字数で分割された文字列と文字数の配列（禁則処理対象行は分割文字数が少なくなる）
 *                array( array( 分割後の文字列, 文字数 ), [....] )
 * @version       0.0.1
 * NOTE
 * UTF-8 以外では意図しない動作になることがある。
 * $encoding utf8の認識パターン例：utf8, uTf8, utf-8, UtF-8
 */
function jp_hyphenation($str , $width , $encoding = "UTF-8")
{

  // 変数の存在及びnull確認 mixed
	if (!isset($str)) {
		return NULL;
	}

	// $widthは1文字以上
	if ($width < 1) {
		return NULL;
	}

	//エンコーディングの指定があれば一時変更する、関数終了時に元に戻す
	//最初 mb_ 系の関数のencodingを個別に指定してたがこちらに変更。弊害があるのかな？
	$internal_encoding = mb_internal_encoding();
	$regex_encoding    = mb_regex_encoding();
	if (isset($encoding)) {
		mb_internal_encoding($encoding);
		mb_regex_encoding($encoding);
	}

	// 禁則パターン設定 ------------------------------------------------
	//mb_ereg系はデミリタを付けない！オプションは第3引数にセット！かなりハマった(*_*;
	//http://www.cnblogs.com/sekihin/archive/2008/08/05/1260771.html
	//
	//分離禁則パターンを格納する配列
	//第1引数 : FALES=分割しない、TRUE=分割する
	//第2引数 : 行末＋次行頭の2文字を検索対象として正規表現パターンを記述。
	//第3引数 : 正規表現のオプション（i:大文字小文字マッチ, s:改行文字を一般文字とする, u:UTF文字として認識）
	//TODO:2文字だけで判定する正規表現の管理が煩雑、もっと簡単な方法があるはず
	$pattern = array( //
		array(FALSE , "[\〳\(\[｛〔〈《「『【〘〖〝‘“｟«—…‥〴〵]." , "") , //行末禁則文字
		//次行頭禁則文字
		array(FALSE , ".[\:\;\/\?\!\‐\゠\–\.\,\)\]｝、〕〉》」』】〙〗〟’”｠»ゝゞ々ーァィゥェォッャュョヮヵヶぁぃぅぇぉっゃゅょゎゕゖㇰㇱㇲㇳㇴㇵㇶㇷㇸㇹㇷ゚ㇺㇻㇼㇽㇾㇿ々〻〜～‼⁇⁈⁉・。]" , "") , array(FALSE , "[a-zａ-ｚＡ-Ｚ'’][a-zａ-ｚＡ-Ｚ'’\.．( |　)]" , "i") , //英単語
		// -$12,000.- , 2/3 , 12+6=18 , 23.01% , 2013/8/28 , 03-1234-5678
		array(FALSE , "[\-－][\\￥\$＄0-9０-９]|[\\￥\$＄\+＋\*＊\/／ ][0-9０-９]|[0-9０-９][0-9０-９,，.\．\*＊\+＋\/／\-－]|[,，.．][0-9０-９\-－]|[0-9０-９%％\-][ 　]" , "") , //数字列
		array(FALSE , "(.\r|.\n|  |　　|\-\-|\.\.|……|‥‥|〳〳|〴〴|〵〵|～～)" , "s") , //分離不可文字
		array(FALSE , "[一-龠]{2}" , "") , //漢字単語
		array(FALSE , "([ァ-ヾ]|[ｧ-ﾝﾞﾟ]){2}" , "") , //カタカナ単語
		array(TRUE , "" , "") //強制分離パターン:強制的に分離させたいパターンを記述
	);
	// ----------------------------------------------------------------

	$p      = $width - 1; //文字を指し示すポインタ mb_substr で1文字目が0なので-1する
	$result = array(); //戻り値用の配列
	$no     = 0; //$result[$no] 戻り値用配列の番号

	// 配列なら連結して文字列にする
	if (is_array($str)) {
		$str = implode($str);
	}

	// 分割予定文字数の場所にポインタを配置して、分割予定の行末、次行頭文字を禁則文字と比較して判断する
	do {
		//分割予定の行末文字＋次行頭文字の2文字を格納。禁則パターンの検索対象
		$eb = mb_substr($str , $p , 1).mb_substr($str , $p + 1 , 1);

		/*
		$a0 = $eb;
		$a1 = $pattern[0][0] === mb_ereg_match($pattern[0][1] , $eb , $pattern[0][2]);
		$a2 = $pattern[1][0] === mb_ereg_match($pattern[1][1] , $eb , $pattern[1][2]);
		$a3 = $pattern[2][0] === mb_ereg_match($pattern[2][1] , $eb , $pattern[2][2]);
		$a4 = $pattern[3][0] === mb_ereg_match($pattern[3][1] , $eb , $pattern[3][2]);
		$a5 = $pattern[4][0] === mb_ereg_match($pattern[4][1] , $eb , $pattern[4][2]);
		$a6 = $pattern[5][0] === mb_ereg_match($pattern[5][1] , $eb , $pattern[5][2]);
		$a7 = $pattern[6][0] === mb_ereg_match($pattern[6][1] , $eb , $pattern[6][2]);
		$a8 = $pattern[7][0] === mb_ereg_match($pattern[7][1] , $eb , $pattern[7][2]);
		*/

		//禁則パターン配列を元に分割判定、
		$split = TRUE;
		foreach ($pattern as $hyp) {
			$split = $split && ($hyp[0] === mb_ereg_match($hyp[1] , $eb , $hyp[2]));
		}

		if ($split) {
			//分割処理、分割して戻り値配列へ格納する、文字数も格納
			$res         = mb_substr($str , 0 , $p + 1);
			$result[$no] = array($res , mb_strlen($res));
			$no++;
			//残りの文字列を切り出す
			$str = mb_substr($str , $p + 1);

			//残りの文字列が分割文字数より小さければポインタを修正する
			$p = $width < mb_strlen($str) ? $width - 1 : mb_strlen($str) - 1;

		} else {
			//禁則パターンがあったのでポインタを前方へ戻す
			$p--;
		}
	} while (0 <= $p); //終了時は $p=-1

	//変更を元に戻す
	mb_internal_encoding($internal_encoding);
	mb_regex_encoding($regex_encoding);
	return $result;
}
