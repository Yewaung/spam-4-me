<?php

defined( 'ABSPATH' ) || exit;

function s4m_keyword_lists() {
	static $lists = null;

	if ( null !== $lists ) {
		return $lists;
	}

	$lists = array(
		'adult'     => array(
			'porn*', 'sex', 'sexcam*', 'sex cam*', 'sex chat*', 'sex video*', 'sex tape*', 'sex dating', 'sexy girls', 'hot girls', 'horny',
			'camgirl*', 'webcam girl*', 'nudes', 'nude photo*', 'nude video*', 'naked girl*', 'erotic*', 'hentai', 'milf*', 'onlyfans',
			'adult video*', 'adult dating', 'adult site*', 'adult content', 'escort service*', 'escort girl*', 'escorts', 'call girl*',
			'hookup site*', 'hookup tonight', 'xvideos', 'xnxx', 'pornhub', 'xhamster', 'brazzers', 'dating site*', 'meet singles', 'lonely women',
			'porno', 'sexo', 'putas', 'fick', 'ficken',
			'порно*', 'секс*', 'проститутк*', 'интим*',
			'bokep', 'video mesum', 'phim sex', 'gái gọi',
			'色情', '成人视频', '成人影片', '成人网站', '成人網站', '黄色网站', '黃色網站', '黄片', '黃片', '裸聊', '约炮', '約炮',
			'一夜情', '援交', 'av女优', 'av女優', '情色', '小姐上门', '外围女', '外圍女', '激情视频', '激情視頻',
			'アダルト', 'エロ動画', '無修正', '出会い系', 'セフレ', '援助交際',
			'야동', '조건만남', '출장안마', '출장마사지', '몰카', '성인방송',
			'หนังโป๊', 'คลิปโป๊', 'เว็บโป๊', 'ไซด์ไลน์',
		),
		'profanity' => array(
			'fuck*', 'motherfuck*', 'shit', 'shitty', 'bullshit', 'bitch*', 'bastard*', 'asshole*', 'cunt*', 'dickhead*', 'wanker*',
			'twat*', 'slut*', 'whore*', 'pussy', 'nigger*', 'nigga*', 'faggot*',
			'puta madre', 'hijo de puta', 'pendejo*', 'caralho', 'filho da puta',
			'putain', 'salope*', 'connard*', 'enculé*',
			'scheiße', 'scheisse', 'arschloch*', 'hurensohn*', 'wichser*',
			'блять', 'бля', 'хуй*', 'пизд*', 'ебать*', 'ёбан*',
			'putang ina', 'tangina', 'bangsat', 'kontol', 'memek', 'ngentot', 'bhenchod', 'madarchod', 'chutiya',
			'địt mẹ', 'đụ má',
			'操你妈', '操你媽', '草泥马', '傻逼', '傻屄', '他妈的', '他媽的', '去你妈的', '狗日的', '婊子',
			'씨발', '시발', '개새끼', '병신',
			'เย็ดแม่',
		),
		'gambling'  => array(
			'online casino*', 'casino online', 'casino bonus*', 'free spins', 'sportsbook*', 'betting tips', 'betting site*',
			'poker online', 'baccarat online', 'roulette online', '1xbet', 'slot gacor', 'slot online', 'judi online', 'situs judi',
			'agen judi', 'bandar judi', 'togel*',
			'nhà cái', 'cá độ', 'đánh bạc', 'tài xỉu',
			'казино', 'ставки на спорт', 'букмекер*',
			'赌博', '賭博', '博彩', '百家乐', '百家樂', '时时彩', '時時彩', '六合彩', '网赌', '網賭', '线上赌场', '線上賭場',
			'真人娱乐', '真人娛樂', '彩票平台',
			'オンラインカジノ', 'ネットカジノ',
			'카지노사이트', '토토사이트', '바카라', '먹튀', '슬롯사이트',
			'บาคาร่า', 'สล็อตออนไลน์', 'เว็บพนัน', 'แทงบอล', 'หวยออนไลน์', 'คาสิโนออนไลน์',
		),
		'scam'      => array(
			'bonus account', 'bonus balance', 'withdraw your funds', 'withdraw funds', 'claim your prize', 'claim your reward',
			'you have won', "you've won", 'lottery winner', 'unclaimed fund*', 'inheritance fund*', 'verify your account',
			'account suspended', 'crypto investment*', 'bitcoin investment*', 'double your bitcoin', 'investment opportunit*',
			'forex signal*', 'binary option*', 'trading bot*', 'crypto airdrop', 'token airdrop','earn money online', 'make money online', 'passive income',
			'instant loan*', 'guaranteed loan*', 'share.google',
			'приветствуем', 'бонус*', 'вывести средства', 'выигрыш*', 'ваш счёт', 'ваш счет',
			'刷单', '兼职刷单', '代开发票', '代開發票', '发票代开', '办证', '辦證', '网贷', '網貸', '套现', '套現', '洗钱', '洗錢',
			'恭喜您中奖', '恭喜您中獎', '高额回报', '高額回報', '稳赚', '穩賺',
			'kiếm tiền online', 'vay tiền nhanh', 'pinjol', 'pinjaman online', 'กู้เงินด่วน',
		),
		'pharma'    => array(
			'viagra', 'cialis', 'levitra', 'kamagra', 'tramadol', 'xanax', 'oxycodone', 'oxycontin', 'fentanyl', 'adderall',
			'phentermine', 'online pharmacy', 'pharmacy online', 'buy pills', 'without prescription', 'no prescription needed',
			'weight loss pills', 'keto pills', 'male enhancement', 'penis enlargement',
			'伟哥', '偉哥', '春药', '春藥', '壮阳', '壯陽',
		),
		'seo'       => array(
			'seo service*', 'seo expert*', 'seo agency', 'seo package*', 'backlink*', 'link building', 'guest post*', 'dofollow',
			'do-follow', 'domain authority', 'rank your website', 'first page of google', 'page 1 of google', 'website traffic',
			'increase your traffic', 'organic traffic', 'boost your ranking*',
			'网站优化', '網站優化', '网站推广', '網站推廣', 'seo优化', '百度排名', 'продвижение сайт*',
		),
		'shortener' => array(
			'bit.ly', 'tinyurl', 'goo.gl', 't.me', 'telegra.ph', 'is.gd', 'cutt.ly', 'rb.gy', 'shorturl.at', 'tiny.cc', 'ow.ly',
		),
	);

	return $lists;
}

function s4m_keyword_labels() {
	return array(
		'adult'     => __( 'Adult & porn', 'spam-4-me' ),
		'profanity' => __( 'Swear words', 'spam-4-me' ),
		'gambling'  => __( 'Gambling & betting', 'spam-4-me' ),
		'scam'      => __( 'Scams, phishing & fake bonuses', 'spam-4-me' ),
		'pharma'    => __( 'Pills & pharmacy', 'spam-4-me' ),
		'seo'       => __( 'SEO & marketing pitches', 'spam-4-me' ),
		'shortener' => __( 'Link shorteners & Telegram', 'spam-4-me' ),
		'custom'    => __( 'Your own keywords', 'spam-4-me' ),
	);
}

function s4m_disposable_domains() {
	static $domains = null;

	if ( null !== $domains ) {
		return $domains;
	}

	$domains = array_flip(
		array(
			'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org', 'sharklasers.com', 'grr.la',
			'10minutemail.com', '10minutemail.net', 'temp-mail.org', 'temp-mail.io', 'tempmail.com', 'tempmail.net', 'tempail.com',
			'tmpmail.org', 'tmpmail.net', 'yopmail.com', 'yopmail.fr', 'yopmail.net', 'trashmail.com', 'trashmail.de', 'getnada.com',
			'dispostable.com', 'maildrop.cc', 'throwawaymail.com', 'fakeinbox.com', 'mintemail.com', 'mohmal.com', 'emailondeck.com',
			'mailnesia.com', 'mytemp.email', '1secmail.com', '1secmail.net', 'moakt.com', 'burnermail.io', 'spamgourmet.com',
			'mailcatch.com', 'inboxkitten.com', 'tempr.email', 'discard.email', 'mail.tm', 'emailfake.com', 'spambog.com',
			'mailpoof.com', 'fakemail.net', 'tempinbox.com', 'mvrht.net', 'dropmail.me', 'emltmp.com',
		)
	);

	return $domains;
}
