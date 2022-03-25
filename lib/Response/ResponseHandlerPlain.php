<?php

/**
 * spid-cie-oidc-php
 * https://github.com/italia/spid-cie-oidc-php
 *
 * 2022 Michele D'Amico (damikael)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author     Michele D'Amico <michele.damico@linfaservice.it>
 * @license    http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 */
 
namespace SPID_CIE_OIDC_PHP\Response;

class ResponseHandlerPlain extends ResponseHandler
{
    public function sendResponse(string $redirect_uri, object $data, string $state)
    {
        echo "<form name='spidauth' action='" . $redirect_uri . "' method='POST'>";
        foreach ($data as $attribute => $value) {
            echo "<input type='hidden' name='" . $attribute . "' value='" . $value . "' />";
        }
        echo "<input type='hidden' name='state' value='" . $state . "' />";
        echo "</form>";
        echo "<script type='text/javascript'>";
        echo "  document.spidauth.submit();";
        echo "</script>";
    }
}
