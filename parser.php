<?php

class ResumeParser {
    /**
     * Extracts text from a file based on its extension.
     * 
     * @param string $filePath
     * @return string
     * @throws Exception
     */
    public static function extractText(string $filePath, string $originalName = ""): string {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: " . basename($filePath));
        }

        $extensionSource = !empty($originalName) ? $originalName : $filePath;
        $extension = strtolower(pathinfo($extensionSource, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'txt':
                return self::parseTxt($filePath);
            case 'docx':
                return self::parseDocx($filePath);
            case 'pdf':
                return self::parsePdf($filePath);
            default:
                throw new Exception("Unsupported file format: ." . $extension);
        }
    }

    private static function parseTxt(string $filePath): string {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception("Failed to read TXT file.");
        }
        // Convert to UTF-8 if necessary
        return mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content, 'UTF-8, ISO-8859-1, GBK', true) ?: 'UTF-8');
    }

    private static function parseDocx(string $filePath): string {
        if (!class_exists('ZipArchive')) {
            throw new Exception("PHP ZipArchive extension is not enabled. Cannot parse DOCX files.");
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            $xmlContent = "";
            // In DOCX, main content is in word/document.xml
            $xmlIndex = $zip->locateName('word/document.xml');
            if ($xmlIndex !== false) {
                $xmlContent = $zip->getFromIndex($xmlIndex);
            }
            $zip->close();

            if (!empty($xmlContent)) {
                // Strip tags except w:t (text) and w:p (paragraph for spacing)
                // Using simple XML parsing or DOMDocument to get text reliably
                $dom = new DOMDocument();
                // Disable entity loader for security
                libxml_use_internal_errors(true);
                if ($dom->loadXML($xmlContent)) {
                    $paragraphs = $dom->getElementsByTagName('p');
                    $text = "";
                    foreach ($paragraphs as $p) {
                        $pText = "";
                        $texts = $p->getElementsByTagName('t');
                        foreach ($texts as $t) {
                            $pText .= $t->nodeValue;
                        }
                        if (trim($pText) !== "") {
                            $text .= $pText . "\n";
                        }
                    }
                    return $text;
                }
                libxml_clear_errors();
            }
        }
        throw new Exception("Failed to parse DOCX file content.");
    }

    private static function parsePdf(string $filePath): string {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception("Failed to read PDF file.");
        }

        // 1. Locate all PDF objects
        $objects = self::locatePdfObjects($content);

        // 2. Map Font Objects to ToUnicode Object Numbers
        $fontToUnicode = [];
        foreach ($objects as $id => $objContent) {
            if (preg_match('/\/Type\s*\/Font\b/i', $objContent)) {
                if (preg_match('/\/ToUnicode\s+(\d+)\s+\d+\s+R/i', $objContent, $m)) {
                    $fontToUnicode[$id] = (int)$m[1];
                }
            }
        }

        // 3. Parse ToUnicode CMaps
        $cmaps = [];
        foreach ($fontToUnicode as $fontObjId => $cmapObjId) {
            if (isset($objects[$cmapObjId])) {
                $cmapStream = self::extractPdfStream($objects[$cmapObjId]);
                if ($cmapStream) {
                    $cmaps[$cmapObjId] = self::parsePdfCMap($cmapStream);
                }
            }
        }

        // 4. Find Font mappings in Page Resource structures (e.g. /F1 30 0 R)
        $fontNameMap = [];
        foreach ($objects as $id => $objContent) {
            if (preg_match('/\/Font\s*<<([^>]+)>>/is', $objContent, $m)) {
                $fontDict = $m[1];
                preg_match_all('/\/([A-Za-z0-9_]+)\s+(\d+)\s+\d+\s+R/i', $fontDict, $fontRefMatches);
                foreach ($fontRefMatches[1] as $idx => $fName) {
                    $targetObj = (int)$fontRefMatches[2][$idx];
                    if (isset($fontToUnicode[$targetObj])) {
                        $fontNameMap[$fName] = $fontToUnicode[$targetObj];
                    }
                }
            }
        }

        // 5. Extract Page Content Streams and decode them
        $text = "";
        $pageContentsObjIds = [];
        foreach ($objects as $id => $objContent) {
            if (preg_match('/\/Type\s*\/Page\b/i', $objContent)) {
                if (preg_match('/\/Contents\s+(\d+)\s+\d+\s+R/i', $objContent, $m)) {
                    $pageContentsObjIds[] = (int)$m[1];
                } elseif (preg_match('/\/Contents\s*\[([^\]]+)\]/is', $objContent, $m)) {
                    preg_match_all('/(\d+)\s+\d+\s+R/i', $m[1], $arrMatches);
                    foreach ($arrMatches[1] as $cId) {
                        $pageContentsObjIds[] = (int)$cId;
                    }
                }
            }
        }

        if (empty($pageContentsObjIds)) {
            foreach ($objects as $id => $objContent) {
                if (strpos($objContent, 'BT') !== false && strpos($objContent, 'ET') !== false) {
                    $pageContentsObjIds[] = $id;
                }
            }
        }

        $pageContentsObjIds = array_unique($pageContentsObjIds);
        foreach ($pageContentsObjIds as $cId) {
            if (isset($objects[$cId])) {
                $stream = self::extractPdfStream($objects[$cId]);
                if ($stream) {
                    $text .= self::parsePdfPageStream($stream, $fontNameMap, $cmaps) . "\n";
                }
            }
        }

        // Final cleanup
        $text = trim($text);
        if (empty($text)) {
            // Fallback for extremely basic flat PDFs
            preg_match_all('/\((.*?)\)/', $content, $fallbackMatches);
            $lines = [];
            foreach ($fallbackMatches[1] as $match) {
                $match = trim($match);
                if (strlen($match) > 1 && !preg_match('/^[\x00-\x1F\x7F]+$/', $match)) {
                    $lines[] = $match;
                }
            }
            $text = implode("\n", $lines);
        }

        return $text;
    }

    private static function locatePdfObjects(string $content): array {
        $objects = [];
        preg_match_all('/(\d+)\s+\d+\s+obj\b/i', $content, $matches, PREG_OFFSET_CAPTURE);
        
        $count = count($matches[0]);
        for ($i = 0; $i < $count; $i++) {
            $id = (int)$matches[1][$i][0];
            $startOffset = $matches[0][$i][1];
            $endOffset = strpos($content, 'endobj', $startOffset);
            if ($endOffset !== false) {
                $objects[$id] = substr($content, $startOffset, $endOffset - $startOffset + 6);
            }
        }
        return $objects;
    }

    private static function extractPdfStream(string $objContent) {
        if (preg_match('/stream(.*?)endstream/is', $objContent, $match)) {
            $stream = trim($match[1]);
            $decompressed = @gzuncompress($stream);
            if ($decompressed === false) {
                $decompressed = @gzinflate(substr($stream, 2));
            }
            return ($decompressed !== false) ? $decompressed : $stream;
        }
        return null;
    }

    private static function parsePdfCMap(string $cmapStream): array {
        $map = [];

        // 1. Parse bfchar
        preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/i', $cmapStream, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $idx => $srcHex) {
                $dstHex = $matches[2][$idx];
                $map[strtolower($srcHex)] = self::pdfHexToUtf8($dstHex);
            }
        }

        // 2. Parse bfrange blocks
        preg_match_all('/(\d+)\s+beginbfrange(.*?)endbfrange/is', $cmapStream, $rangeBlocks);
        foreach ($rangeBlocks[2] as $block) {
            // Array format: <start> <end> [ <d1> <d2> ]
            preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*\[([^\]]+)\]/i', $block, $arrayMatches);
            foreach ($arrayMatches[1] as $idx => $startHex) {
                $endHex = $arrayMatches[2][$idx];
                $arrContent = $arrayMatches[3][$idx];
                preg_match_all('/<([0-9A-Fa-f]+)>/i', $arrContent, $hexList);
                
                $startVal = hexdec($startHex);
                $endVal = hexdec($endHex);
                for ($val = $startVal; $val <= $endVal; $val++) {
                    $offset = $val - $startVal;
                    if (isset($hexList[1][$offset])) {
                        $srcKey = str_pad(dechex($val), strlen($startHex), '0', STR_PAD_LEFT);
                        $map[strtolower($srcKey)] = self::pdfHexToUtf8($hexList[1][$offset]);
                    }
                }
            }

            // Normal format: <start> <end> <dstStart>
            preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/i', $block, $normalMatches);
            foreach ($normalMatches[1] as $idx => $startHex) {
                $endHex = $normalMatches[2][$idx];
                $dstStartHex = $normalMatches[3][$idx];
                
                $startVal = hexdec($startHex);
                $endVal = hexdec($endHex);
                $dstStartVal = hexdec($dstStartHex);
                
                for ($val = $startVal; $val <= $endVal; $val++) {
                    $offset = $val - $startVal;
                    $srcKey = str_pad(dechex($val), strlen($startHex), '0', STR_PAD_LEFT);
                    $dstVal = $dstStartVal + $offset;
                    $dstHex = str_pad(dechex($dstVal), strlen($dstStartHex), '0', STR_PAD_LEFT);
                    $map[strtolower($srcKey)] = self::pdfHexToUtf8($dstHex);
                }
            }
        }

        return $map;
    }

    private static function pdfHexToUtf8(string $hex): string {
        $str = '';
        for ($i = 0; $i < strlen($hex); $i += 4) {
            $charHex = substr($hex, $i, 4);
            $codepoint = hexdec($charHex);
            $str .= self::pdfCodepointToUtf8($codepoint);
        }
        return $str;
    }

    private static function pdfCodepointToUtf8(int $num): string {
        if ($num < 128) return chr($num);
        if ($num < 2048) return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
        if ($num < 65536) return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
        if ($num < 2097152) return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
        return '';
    }

    private static function parsePdfPageStream(string $stream, array $fontNameMap, array $cmaps): string {
        $text = "";
        preg_match_all('/BT(.*?)ET/is', $stream, $btMatches);
        
        foreach ($btMatches[1] as $textBlock) {
            $currentCMapId = null;
            $lines = explode("\n", $textBlock);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                if (preg_match('/\/([A-Za-z0-9_]+)\s+[\d\.]+\s+Tf/i', $line, $fontMatch)) {
                    $fontName = $fontMatch[1];
                    $currentCMapId = isset($fontNameMap[$fontName]) ? $fontNameMap[$fontName] : null;
                }
                
                if (preg_match('/\[(.*?)\]\s*TJ/i', $line, $tjMatch)) {
                    $tjContent = $tjMatch[1];
                    preg_match_all('/<([0-9A-Fa-f]+)>|\((.*?)\)/', $tjContent, $elements);
                    foreach ($elements[0] as $eIdx => $fullElem) {
                        if (strpos($fullElem, '<') === 0) {
                            $hex = $elements[1][$eIdx];
                            $text .= self::decodePdfHexTokens($hex, $currentCMapId, $cmaps);
                        } else {
                            $plain = $elements[2][$eIdx];
                            // Clean up escapes
                            $plain = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $plain);
                            $text .= $plain;
                        }
                    }
                    $text .= " ";
                }
                elseif (preg_match('/<([0-9A-Fa-f]+)>\s*Tj/i', $line, $tjMatch)) {
                    $hex = $tjMatch[1];
                    $text .= self::decodePdfHexTokens($hex, $currentCMapId, $cmaps) . " ";
                }
                elseif (preg_match('/\((.*?)\)\s*Tj/i', $line, $tjMatch)) {
                    $plain = $tjMatch[1];
                    $plain = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $plain);
                    $text .= $plain . " ";
                }
            }
            $text .= "\n";
        }
        return $text;
    }

    private static function decodePdfHexTokens(string $hex, $cmapId, array $cmaps): string {
        $out = "";
        $len = strlen($hex);
        for ($i = 0; $i < $len; $i += 4) {
            $token = strtolower(substr($hex, $i, 4));
            if ($cmapId !== null && isset($cmaps[$cmapId][$token])) {
                $out .= $cmaps[$cmapId][$token];
            } else {
                $val = hexdec($token);
                $out .= self::pdfCodepointToUtf8($val);
            }
        }
        return $out;
    }
}
