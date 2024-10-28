<?php
/**
 * Copyright (c) 2020, AudiencePlayer
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS "AS IS" AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @license     Berkeley Software Distribution License (BSD-License 2) http://www.opensource.org/licenses/bsd-license.php
 * @author      AudiencePlayer <support@audienceplayer.com>
 * @copyright   AudiencePlayer
 * @link        https://www.audienceplayer.com
 */

namespace AudiencePlayer\AudiencePlayerWordpressPlugin\Resources;

use AudiencePlayer\AudiencePlayerWordpressPlugin\Config\Constants;
use Closure;

trait CacheTrait
{
    public function assembleCacheKey(array $cacheProperties = [])
    {
        return Constants::PROJECT_CONFIG_NAMESPACE_PREFIX . '_' . md5(serialize($cacheProperties));
    }

    protected function syncCacheValue(string $cacheKey, $newValue = null, bool $forceReload = false, int $ttl = 300)
    {
        $value = \get_site_option($cacheKey, '');

        if (
            false === $forceReload &&
            ($value->timestamp ?? null) &&
            (\time() - $value->timestamp < $ttl)
        ) {

            return $value;

        } elseif ($newValue) {

            if ($value = $newValue instanceof Closure ? $newValue() : $newValue) {
                $value->timestamp = \time();
            }

            // Simply always use update_option (which internally calls add_option when needed)
            // https://www.wpdevhub.com/2019/wordpress-add_option-versus-update_option/
            \update_option($cacheKey, $value);
        }

        return $value;
    }

}
