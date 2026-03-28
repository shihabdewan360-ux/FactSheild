<?php
require_once 'includes/config.php';

// ============================================================
// KEYS
// ============================================================
define('GEMINI_KEY',          'AIzaSyDZmvj8JR7jDaKOSHoiMEe7EuEGc_EDmMU');
define('FACT_CHECK_KEY',      'AIzaSyCfQRMu388zEp0y4zO-Xb46whsOVeI2aNU');
define('GOOGLE_SEARCH_KEY',   'AIzaSyCfQRMu388zEp0y4zO-Xb46whsOVeI2aNU');
define('GOOGLE_SEARCH_CX',    'a60769f4f15c147f7'); 
define('NEWS_API_KEY',        '804bc0920d4e4306b3bb16c4fcb8dc8a');

define('CACHE_DIR', __DIR__ . '/cache/');
define('LOG_DIR',   __DIR__ . '/logs/');
define('CACHE_TTL', 3600);

foreach ([CACHE_DIR, LOG_DIR] as $dir) { if (!is_dir($dir)) @mkdir($dir, 0755, true); }

function fsLog(string $l, string $m, array $c = []): void {
    $line = date('Y-m-d H:i:s') . " [$l] $m";
    if ($c) $line .= ' | ' . json_encode($c, JSON_UNESCAPED_SLASHES);
    @file_put_contents(LOG_DIR . 'factshield.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function cKey(string $p, string $d): string { return CACHE_DIR . $p . '_' . md5($d) . '.json'; }
function cGet(string $f) {
    if (!file_exists($f)) return null;
    if ((time()-filemtime($f)) > CACHE_TTL) { @unlink($f); return null; }
    $r = file_get_contents($f); return $r ? json_decode($r, true) : null;
}
function cSet(string $f, $d): void { @file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE), LOCK_EX); }

function httpPost(string $url, array $payload, array $headers = ['Content-Type: application/json'], int $timeout = 45): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>$headers, CURLOPT_POSTFIELDS=>json_encode($payload),
        CURLOPT_TIMEOUT=>$timeout, CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; FactShield/2.0)']);
    $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch); curl_close($ch); return compact('code','body','err');
}
function httpGet(string $url, int $timeout = 12): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>$timeout,
        CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; FactShield/2.0)']);
    $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch); curl_close($ch); return compact('code','body','err');
}

function extractJson(string $text): ?array {
    if (preg_match('/```json\s*([\s\S]*?)\s*```/i', $text, $m)) { $d = json_decode(trim($m[1]),true); if ($d) return $d; }
    if (preg_match('/```\s*([\s\S]*?)\s*```/i', $text, $m))     { $d = json_decode(trim($m[1]),true); if ($d) return $d; }
    $s = strpos($text,'{'); $e = strrpos($text,'}');
    if ($s!==false && $e!==false && $e>$s) { $d = json_decode(substr($text,$s,$e-$s+1),true); if ($d) return $d; }
    $d = json_decode(trim($text),true); if ($d) return $d;
    $clean = preg_replace('/[\x00-\x1F\x7F]/u','',$text);
    $s = strpos($clean,'{'); $e = strrpos($clean,'}');
    if ($s!==false && $e!==false) { $d = json_decode(substr($clean,$s,$e-$s+1),true); if ($d) return $d; }
    return null;
}

// ── Google Custom Search (free 100/day) ───────────────────────────────────
function googleSearch(string $query, int $num = 5): array {
    if (GOOGLE_SEARCH_CX === 'YOUR_SEARCH_ENGINE_ID_HERE') return [];
    $ck = cKey('gs', $query.$num); $cached = cGet($ck); if ($cached!==null) return $cached;
    $res = httpGet('https://www.googleapis.com/customsearch/v1?'.http_build_query([
        'key'=>GOOGLE_SEARCH_KEY, 'cx'=>GOOGLE_SEARCH_CX, 'q'=>$query, 'num'=>$num]));
    if ($res['code']!==200) { fsLog('WARN','Google Search failed',['code'=>$res['code'],'body'=>substr($res['body'],0,200)]); return []; }
    $items = json_decode($res['body'],true)['items'] ?? [];
    $results = array_map(fn($i) => [
        'title'  => $i['title']??'', 'snippet' => $i['snippet']??'',
        'url'    => $i['link']??'',  'source'  => parse_url($i['link']??'', PHP_URL_HOST)
    ], $items);
    cSet($ck, $results);
    fsLog('INFO','Google Search OK',['results'=>count($results)]);
    return $results;
}

function wikipediaSummary(string $query): string {
    $ck = cKey('wiki', $query); $cached = cGet($ck);
    if ($cached!==null) return $cached['text']??'';
    
    // Shorten query to avoid Wikipedia API 404s on long sentences
    $shortQuery = mb_substr($query, 0, 50); 
    $url = 'https://en.wikipedia.org/api/rest_v1/page/summary/'.urlencode(str_replace(' ','_',$shortQuery));
    $res = httpGet($url, 8);
    
    if ($res['code']!==200) {
        // Removed the aggressive srsearch fallback that was pulling unrelated entities
        return '';
    }
    
    $text = mb_substr(json_decode($res['body'],true)['extract']??'', 0, 600);
    cSet($ck, ['text'=>$text]);
    return $text;
}

// ── Google Fact Check (free) ──────────────────────────────────────────────
function fetchFactCheck(string $query): array {
    $ck = cKey('fc', $query); $cached = cGet($ck); if ($cached!==null) return $cached;
    $res = httpGet('https://factchecktools.googleapis.com/v1alpha1/claims:search?'.http_build_query(['query'=>$query,'key'=>FACT_CHECK_KEY]));
    if ($res['code']!==200) return [];
    $claims = json_decode($res['body'],true)['claims']??[];
    cSet($ck, $claims); return $claims;
}

function fetchNews(string $query): array {
    $ck = cKey('news', $query); $cached = cGet($ck); if ($cached!==null) return $cached;
    
    // Clean newlines/tabs from the query so the API doesn't reject the request
    $cleanQuery = trim(preg_replace('/\s+/', ' ', mb_substr($query,0,100)));
    
    $res = httpGet('https://newsapi.org/v2/everything?'.http_build_query([
        'q' => $cleanQuery,
        'language' => 'en',
        'pageSize' => 5,
        'sortBy' => 'relevancy', // Prioritize best matches over random dates
        'apiKey' => NEWS_API_KEY
    ]));
    
    if ($res['code']!==200) return [];
    $articles = json_decode($res['body'],true)['articles']??[];
    cSet($ck, $articles); return $articles;
}

function callGeminiWithContext(string $content, string $evidenceBlock): ?array {
    $prompt = "You are a professional fact-checking AI. Real evidence has been gathered for you below.\n\n".
        "=== CONTENT TO FACT-CHECK ===\n{$content}\n\n".
        "=== REAL EVIDENCE FROM THE WEB ===\n{$evidenceBlock}\n\n".
        "Fact-check up to the TOP 5 most important claims using the provided evidence AND your own internal knowledge if the evidence is insufficient.\n".
        "Return ONLY a JSON object matching this schema:\n".
        '{"verdict":"real|fake|suspicious","confidence":<1-100>,"ai_summary":"<2-3 sentences>",'.
        '"claims":[{"claim":"<text>","verdict":"true|false|unverified","explanation":"<1-2 sentences>",'.
        '"sources":[{"source_name":"<n>","source_url":"<url>"}]}]}';

   $model = 'gemini-2.5-flash'; // Updated to the current live model version
    
    $res = httpPost("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=".GEMINI_KEY, [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature' => 0.1,
            'maxOutputTokens' => 8192, // Increased from 2048 to prevent JSON truncation
            'responseMimeType' => 'application/json' 
        ]
    ]);
    
    if ($res['code'] !== 200) {
        return ['verdict' => 'suspicious', 'confidence' => 0, 'claims' => [], 'ai_summary' => "API Connection Rejected. (Code: {$res['code']})"];
    }

    $rawText = json_decode($res['body'],true)['candidates'][0]['content']['parts'][0]['text']??null;
    
    if (!$rawText) {
        return ['verdict' => 'suspicious', 'confidence' => 0, 'claims' => [], 'ai_summary' => "Gemini returned no text. This might be due to a safety filter."];
    }

    $parsed = extractJson($rawText); 
    
    if (!$parsed) {
         // Added better error messaging so you know if it's truncation or bad structure
         return ['verdict' => 'suspicious', 'confidence' => 0, 'claims' => [], 'ai_summary' => "Failed to parse JSON. Output may have been truncated or malformed."];
    }

    fsLog('INFO',"Gemini OK ($model)",['verdict'=>$parsed['verdict']??'?']); 
    return $parsed;
}

$TRUSTED_DOMAINS = [
    'bbc.com', 'bbc.co.uk', 'reuters.com', 'who.int', 'cdc.gov', 'apnews.com',
    'nytimes.com', 'theguardian.com', 'foxnews.com', 'aljazeera.com',
    'thestar.com.my', 'bharian.com.my', 'sinchew.com.my', 'snopes.com',
    'factcheck.org', 'politifact.com', 'fullfact.org', 'wikipedia.org',
    'time.com', 'theatlantic.com', 'usatoday.com', 'afp.com', 'thehindu.com',
    'forbes.com', 'indianexpress.com', 'france24.com', 'cnn.com', 'hmetro.com.my',
    'nst.com.my', 'chinapress.com.my', 'themalaysianreserve.com', 'thedailystar.net',
    'jugantor.com', 'prothomalo.com', 'samakal.com', 'cctv.com', 'news.cn',
    'sina.com.cn', 'xinhuanet.com', 'scimagomedia.com', 'hindustantimes.com',
    'timesofindia.indiatimes.com', 'business-standard.com', 'zeenews.india.com'
];


function isTrusted(string $url, array $doms): bool { foreach ($doms as $d) { if (stripos($url,$d)!==false) return true; } return false; }


function extractFromUrl(string $url): array {
    // Fetch the webpage HTML
    $res = httpGet($url, 15);
    if ($res['code'] !== 200 || empty($res['body'])) return ['title' => '', 'text' => ''];
    
    $html = $res['body'];
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    
    // Extract Title
    $title = '';
    $titleNodes = $dom->getElementsByTagName('title');
    if ($titleNodes->length > 0) $title = trim($titleNodes->item(0)->textContent);
    
    // Extract only readable paragraphs, skipping short nav/menu links
    $paragraphs = [];
    $pNodes = $dom->getElementsByTagName('p');
    foreach ($pNodes as $p) {
        $t = trim($p->textContent);
        if (strlen($t) > 40) $paragraphs[] = $t;
    }
    
    return [
        'title' => $title,
        'text' => $title . "\n\n" . implode("\n", $paragraphs)
    ];
}



function extractFromVideoLink(string $url): array {
    // 1. YouTube OEmbed (Public, no API key needed)
    if (stripos($url, 'youtube.com') !== false || stripos($url, 'youtu.be') !== false) {
        $oembed = httpGet('https://www.youtube.com/oembed?url=' . urlencode($url) . '&format=json', 8);
        if ($oembed['code'] === 200) {
            $d = json_decode($oembed['body'], true);
            $title = $d['title'] ?? '';
            $author = $d['author_name'] ?? '';
            return [
                'title' => $title,
                'text' => "Video Title: {$title}\nChannel: {$author}\nLink: {$url}\n\nPlease analyze and fact-check the likely claims made in this specific viral video based on its title and context."
            ];
        }
    }
    
    // 2. TikTok OEmbed
    if (stripos($url, 'tiktok.com') !== false) {
        $oembed = httpGet('https://www.tiktok.com/oembed?url=' . urlencode($url), 8);
        if ($oembed['code'] === 200) {
            $d = json_decode($oembed['body'], true);
            $title = $d['title'] ?? '';
            $author = $d['author_name'] ?? '';
            return [
                'title' => mb_substr($title, 0, 100), // TikTok titles can be long captions
                'text' => "TikTok Caption/Title: {$title}\nCreator: {$author}\nLink: {$url}\n\nPlease analyze and fact-check the claims made in this TikTok video."
            ];
        }
    }
    
    // 3. Fallback (If it's Vimeo, Rumble, or Twitter, try the standard web scraper)
    return extractFromUrl($url);
}

// ── ENTRY POINT ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']!=='POST' || empty($_POST['content']) || empty($_POST['type'])) { header('Location: /'); exit; }

$rawInput = trim($_POST['content']);
$type     = $_POST['type'];

$searchQuery = $rawInput; 
$geminiContent = $rawInput; 

// If URL tab was used, scrape the site first
if ($type === 'url') {
    if (!filter_var($rawInput, FILTER_VALIDATE_URL)) {
        echo "<script>alert('Invalid URL format.'); window.location.href='/';</script>"; exit;
    }
    $extracted = extractFromUrl($rawInput);
    if (empty($extracted['text'])) {
        echo "<script>alert('Failed to read content. The site may be blocking access.'); window.location.href='/';</script>"; exit;
    }
    
    // Use the Title for Google/News searches, and the full text for Gemini
    $searchQuery = $extracted['title'] ?: mb_substr($extracted['text'], 0, 100);
    $geminiContent = mb_substr($extracted['text'], 0, 15000); // Cap at 15k chars for API limits
}



// If Video tab was used, extract meta via oEmbed
if ($type === 'video') {
    if (!filter_var($rawInput, FILTER_VALIDATE_URL)) {
        echo "<script>alert('Invalid Video URL format.'); window.location.href='/';</script>"; exit;
    }
    $extracted = extractFromVideoLink($rawInput);
    if (empty($extracted['text'])) {
        echo "<script>alert('Failed to read video metadata. The platform may be blocking access.'); window.location.href='/';</script>"; exit;
    }
    
    // Use the Title for Google/News searches, and the full text for Gemini
    $searchQuery = $extracted['title'] ?: mb_substr($extracted['text'], 0, 100);
    $geminiContent = mb_substr($extracted['text'], 0, 15000);
}

// Ensure the search query is short so it doesn't break Google/News APIs with 414 URI Too Long errors
$searchQuery = mb_substr(preg_replace('/\s+/', ' ', $searchQuery), 0, 150);

$stmt = $pdo->prepare("INSERT INTO articles (content_type,content,verdict,confidence,ai_summary) VALUES (?,?,'suspicious',0,'Processing...')");
$stmt->execute([$type, $rawInput]);
$articleId = $pdo->lastInsertId();

// ── Gather free evidence ──────────────────────────────────────────────────
$evidenceParts = [];
$searchResults = googleSearch($searchQuery, 5);
if ($searchResults) {
    $evidenceParts[] = "=== GOOGLE SEARCH RESULTS ===";
    foreach ($searchResults as $i => $r) {
        $evidenceParts[] = ($i+1).". [{$r['source']}] {$r['title']}\n   {$r['snippet']}\n   URL: {$r['url']}";
    }
}
$wikiText = wikipediaSummary($searchQuery);
if ($wikiText) $evidenceParts[] = "\n=== WIKIPEDIA ===\n".$wikiText;

$factChecks = fetchFactCheck($searchQuery);
if ($factChecks) {
    $evidenceParts[] = "\n=== OFFICIAL FACT CHECKS ===";
    foreach (array_slice($factChecks,0,4) as $fc) {
        $r = $fc['claimReview'][0]??null; if (!$r) continue;
        $evidenceParts[] = "- [{$r['publisher']['name']}] \"{$fc['text']}\" → {$r['textualRating']}\n  URL: {$r['url']}";
    }
}
$newsResults = fetchNews($searchQuery);
if ($newsResults) {
    $evidenceParts[] = "\n=== RELATED NEWS ===";
    foreach (array_slice($newsResults,0,4) as $n) {
        $evidenceParts[] = "- [{$n['source']['name']}] {$n['title']}\n  URL: {$n['url']}";
    }
}
if (empty($evidenceParts)) $evidenceParts[] = "No external evidence found. Analyze from knowledge only; set lower confidence.";
$evidenceBlock = implode("\n", $evidenceParts);


fsLog('INFO','Evidence gathered',['search'=>count($searchResults),'wiki'=>strlen($wikiText)>0?'yes':'no','fc'=>count($factChecks),'news'=>count($newsResults)]);

// ── Call Gemini ───────────────────────────────────────────────────────────
$aiData = callGeminiWithContext($geminiContent, $evidenceBlock);
if (!$aiData) {
    fsLog('ERROR','All AI failed');
    $aiData = ['verdict'=>'suspicious','confidence'=>40,'ai_summary'=>'Analysis temporarily unavailable. Please try again.','claims'=>[]];
}

// ── Validate + normalise ──────────────────────────────────────────────────
if (!in_array(strtolower($aiData['verdict']??''),['real','fake','suspicious'],true)) $aiData['verdict']='suspicious';

$validClaims=[]; $trueCount=$falseCount=$unverifiedCount=0;
foreach ($aiData['claims']??[] as $c) {
    if (!isset($c['claim'],$c['verdict'])) continue;
    $v=strtolower($c['verdict']);
    if (!in_array($v,['true','false','unverified'],true)) continue;
    if ($v==='true') $trueCount++; if ($v==='false') $falseCount++; if ($v==='unverified') $unverifiedCount++;
    $validClaims[]=$c;
}
$aiData['claims']=$validClaims; $totalClaims=count($validClaims);

if ($totalClaims>0) {
    $fr=$falseCount/$totalClaims; $tr=$trueCount/$totalClaims;
    $aiData['verdict'] = $fr>=0.6?'fake':($tr>=0.7&&!$falseCount?'real':'suspicious');
    $aiData['confidence'] = min(95,max(50,intval(max($tr,$fr)*100)));
}

// ── External cross-check ──────────────────────────────────────────────────
$externalMatches=0; $totalChecked=0; $mediaMentions=[];
foreach ($aiData['claims'] as &$claim) {
    $claim['external_sources']=[]; $uniqueMedia=[]; $claimText=strtolower($claim['claim']);
    foreach ($factChecks as $fc) {
        $r=$fc['claimReview'][0]??null; if (!$r) continue;
        $pub=$r['publisher']['name']??'Google Fact Check';
        $claim['external_sources'][]=['title'=>'FACT CHECK: '.($r['title']??''),'url'=>$r['url'],'source'=>$pub];
        $claim['sources'][]=['source_name'=>$pub,'source_url'=>$r['url']];
        $externalMatches+=2; $uniqueMedia[$pub]=true;
    }
    foreach ($newsResults as $news) {
        similar_text(strtolower($news['title']??''), $claimText, $sim);
        if ($sim>30) { $externalMatches++; $uniqueMedia[$news['source']['name']]=true; }
        $claim['external_sources'][]=['title'=>$news['title'],'url'=>$news['url'],'source'=>$news['source']['name']];
    }
    if (count($uniqueMedia)>=2) $externalMatches+=2;
    $mediaMentions[]=count($uniqueMedia); $totalChecked++;
}
unset($claim);

// ── Credibility Score ─────────────────────────────────────────────────────
$score=0;
if ($totalClaims>0) $score+=($trueCount/$totalClaims)*40;
$tc=$ts=0;
foreach ($aiData['claims'] as $c) { foreach ($c['sources']??[] as $s) { $ts++; if (isTrusted($s['source_url']??'',$TRUSTED_DOMAINS)) $tc++; } }
if ($ts>0) $score+=($tc/$ts)*25;
if ($totalChecked>0) $score+=min(20,(array_sum($mediaMentions)/max(1,count($mediaMentions))/3)*20);
$score+=($aiData['confidence']/100)*15;
$credibilityScore=round(min(100,$score));

// ── Persist ───────────────────────────────────────────────────────────────
$blockchainTx='0x'.hash('sha256',$content.time().'FACTSHIELD');
$pdo->prepare("UPDATE articles SET verdict=?,confidence=?,credibility_score=?,ai_summary=?,blockchain_tx=? WHERE id=?")
    ->execute([strtolower($aiData['verdict']),$aiData['confidence'],$credibilityScore,$aiData['ai_summary'],$blockchainTx,$articleId]);

    foreach ($aiData['claims'] as $c) {
        $pdo->prepare("INSERT INTO claims (article_id,claim_text,verdict,explanation) VALUES (?,?,?,?)")
            ->execute([$articleId,$c['claim'],strtolower($c['verdict']),$c['explanation']??'']);
        $claimId=$pdo->lastInsertId();
        foreach ($c['sources']??[] as $s) {
            $sUrl=$s['source_url']??'';
            // Removed the isTrusted() block here. We want to save and show ALL valid URLs the AI used.
            if (!$sUrl||!filter_var($sUrl,FILTER_VALIDATE_URL)) continue;
            $pdo->prepare("INSERT INTO sources (claim_id,source_name,source_url) VALUES (?,?,?)")
                ->execute([$claimId,$s['source_name'],$sUrl]);
        }
    }

header('Location: results.php?id='.$articleId);
exit;