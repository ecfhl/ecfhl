<?php
namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Database originals are durable; generated files can be rebuilt on every deploy. */
final class TeamImages
{
    private static function directory(string $slug): string
    {
        if (!preg_match('/^[A-Za-z0-9-]+$/D', $slug)) throw new \InvalidArgumentException('Invalid image slug.');
        return public_path('media/team-icons/'.$slug);
    }

    private static function manifest(string $slug): ?array
    {
        $path = self::directory($slug).'/manifest.json';
        return is_file($path) ? json_decode(file_get_contents($path), true) : null;
    }

    private static function write(string $path, string $bytes): void
    {
        $temp = tempnam(dirname($path), 'image-');
        try {
            if (file_put_contents($temp, $bytes) === false) throw new \RuntimeException('Could not save generated image.');
            chmod($temp, 0644);
            if (!rename($temp, $path)) throw new \RuntimeException('Could not publish generated image.');
        } finally {
            if (is_file($temp)) unlink($temp);
        }
    }

    public static function generate(string $slug, string $bytes, string $mime): array
    {
        $directory = self::directory($slug);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new \RuntimeException('Could not create image directory.');
        $revision = substr(hash('sha256', $bytes), 0, 20);
        $extension = match($mime) {'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/svg+xml'=>'svg',default=>throw new \InvalidArgumentException('Unsupported image type.')};
        $full = $revision.'.'.$extension;
        self::write($directory.'/'.$full, $bytes);
        $manifest = ['full'=>$full,'mime'=>$mime,'revision'=>$revision,'thumbnails'=>[]];
        if ($mime === 'image/svg+xml') {
            foreach ([64,160,640] as $size) $manifest['thumbnails'][$size] = $full;
        } else {
            $dimensions = @getimagesizefromstring($bytes);
            if (!$dimensions || $dimensions[0]*$dimensions[1] > 16000000) throw new \InvalidArgumentException('Use an image with at most 16 million pixels.');
            $source = @imagecreatefromstring($bytes);
            if (!$source) throw new \InvalidArgumentException('Could not decode image.');
            try {
                foreach ([64,160,640] as $size) {
                    $thumbnail = imagecreatetruecolor($size, $size);
                    imagealphablending($thumbnail, false); imagesavealpha($thumbnail, true);
                    imagefill($thumbnail, 0, 0, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
                    $scale = min($size/$dimensions[0], $size/$dimensions[1]);
                    $width = max(1, (int)round($dimensions[0]*$scale)); $height = max(1, (int)round($dimensions[1]*$scale));
                    imagecopyresampled($thumbnail, $source, (int)(($size-$width)/2), (int)(($size-$height)/2), 0, 0, $width, $height, $dimensions[0], $dimensions[1]);
                    ob_start(); imagewebp($thumbnail, null, 78); $output = ob_get_clean(); imagedestroy($thumbnail);
                    $filename = $revision.'-'.$size.'.webp';
                    self::write($directory.'/'.$filename, $output);
                    $manifest['thumbnails'][$size] = $filename;
                }
            } finally { imagedestroy($source); }
        }
        // Publish only after every size is ready; concurrent readers keep the old revision.
        self::write($directory.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        return $manifest;
    }

    private static function load(string $slug): array
    {
        if ($manifest = self::manifest($slug)) return $manifest;
        $icon = DB::table('team_icons')->where('team_slug', $slug)->first(['mime_type','image_data']);
        if ($icon && ($bytes = base64_decode($icon->image_data, true)) !== false) return self::generate($slug, $bytes, $icon->mime_type);
        $fallback = match($slug) {
            'lineup-advisor'=>'images/lineup-advisor-cartoon.svg',
            'lineup-advisor-pierre'=>'images/pierre-advisor.webp',
            'lineup-advisor-john'=>'images/john-advisor.webp',
            'orcas'=>'images/team-icons/orcas.webp',default=>null,
        };
        if ($fallback && is_file(public_path($fallback))) return self::generate($slug, file_get_contents(public_path($fallback)), str_ends_with($fallback,'.svg')?'image/svg+xml':'image/webp');
        return self::generate($slug, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" rx="20" fill="#e2e8f0"/><path d="M24 31l16-10 10 8 10-8 16 10-8 14-8-5v36H40V40l-8 5-8-14z" fill="#0b5f9e"/><circle cx="50" cy="59" r="11" fill="#fff"/></svg>', 'image/svg+xml');
    }

    public static function url(string $slug, ?int $size = null): string
    {
        $manifest = self::manifest($slug);
        if (!$manifest) return '/team-icons/'.$slug.($size ? '/thumbnail?size='.$size : '');
        $filename = $size ? $manifest['thumbnails'][self::size($size)] : $manifest['full'];
        return '/media/team-icons/'.$slug.'/'.$filename;
    }

    private static function size(int $size): int { return $size <= 64 ? 64 : ($size <= 160 ? 160 : 640); }

    public static function response(Request $request, string $slug, ?int $size = null)
    {
        $manifest = self::load($slug);
        $filename = $size ? $manifest['thumbnails'][self::size($size)] : $manifest['full'];
        $response = response()->file(self::directory($slug).'/'.$filename, ['Cache-Control'=>'public, max-age=300', 'Content-Type'=>str_ends_with($filename,'.webp')?'image/webp':$manifest['mime']]);
        $response->setEtag($manifest['revision'].($size ? '-'.self::size($size) : ''));
        $response->isNotModified($request);
        return $response;
    }

    public static function warm(): int
    {
        $count = 0;
        // PDO MySQL buffers cursor results; fetch one original at a time instead.
        foreach (DB::table('team_icons')->pluck('team_slug') as $slug) {
            $icon = DB::table('team_icons')->where('team_slug',$slug)->first(['team_slug','image_data','mime_type']);
            if (!$icon) continue;
            try {
                self::generate($icon->team_slug, base64_decode($icon->image_data, true), $icon->mime_type); $count++;
            } catch (\Throwable $e) { report($e); }
        }
        foreach (array_merge(PublicData::teamMenu(), ['lineup-advisor','lineup-advisor-pierre','lineup-advisor-john','orcas']) as $name) {
            self::load(\Illuminate\Support\Str::slug($name));
        }
        return $count;
    }
}
