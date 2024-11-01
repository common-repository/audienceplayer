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

declare(strict_types=1);

namespace AudiencePlayer\AudiencePlayerWordpressPlugin;

class AutoLoader
{
    /**
     * @codeCoverageIgnore
     * @param $className
     */
    public static function autoload($className)
    {
        $prefix = __NAMESPACE__ . '\\';
        $prefixLength = strlen($prefix);

        if (0 === strpos($className, $prefix)) {
            $parts = explode('\\', substr($className, $prefixLength));
            $filePath = __DIR__ . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts) . '.php';

            if (is_file($filePath)) {
                require $filePath;
            }
        }
    }

    /**
     * @codeCoverageIgnore
     * @return bool
     */
    public static function register()
    {
        return spl_autoload_register(array(__CLASS__, "autoload"));
    }

    /**
     * @codeCoverageIgnore
     * @return bool
     */
    public static function unregister()
    {
        return spl_autoload_unregister(array(__CLASS__, "autoload"));
    }
}

AutoLoader::register();
