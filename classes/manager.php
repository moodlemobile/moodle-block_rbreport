<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
//
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * Class manager.
 *
 * @package     block_rbreport
 * @author      Mikel Martín <mikel@moodle.com>
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
namespace block_rbreport;

use moodle_url;
use tool_reportbuilder\permission;

/**
 * Class manager.
 *
 * @package     block_rbreport
 * @author      Mikel Martín <mikel@moodle.com>
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager {
    /**
     * List of available reports
     *
     * @param string $pagetype
     * @param string|null $subpage
     * @param moodle_url $pageurl
     * @return string[]
     */
    public function get_report_options(string $pagetype, ?string $subpage, moodle_url $pageurl): array {
        global $DB;

        $mypage = $DB->get_record('my_pages', ['id' => (int)$subpage]);

        if ($pagetype == 'my-index' && $pageurl->compare(new \moodle_url('/my/indexsys.php'), URL_MATCH_BASE)) {
            return $this->get_shared_reports();
        } else if (!empty($mypage) && preg_match('/^tenant-([0-9]+)$/', $mypage->name, $matches, PREG_UNMATCHED_AS_NULL)) {
            return $this->get_tenant_reports((int)$matches[1]);
        } else {
            return $this->get_tenant_reports(\tool_tenant\tenancy::get_actual_tenant_id());
        }
    }

    /**
     * Returns user available reports of a tenant.
     *
     * @param int $tenantid
     * @return string[]
     */
    private function get_tenant_reports($tenantid): array {
        global $DB;

        [$select, $selectparams] = \tool_tenant\hierarchy::filter_own_or_parent_shared_entities_sql('tenantid',
            'shared=1', $tenantid);
        if (!permission::can_view_any()) {
            $allowedreports = \tool_reportbuilder\local\helpers\audience::user_reports_list();
            if (empty($allowedreports)) {
                return [];
            }
            [$insql, $inparams] = $DB->get_in_or_equal($allowedreports, SQL_PARAMS_NAMED);
            $select .= "AND id $insql";
            $selectparams = array_merge($selectparams, $inparams);
        }

        $sql = "SELECT id, name FROM {tool_reportbuilder}
                    WHERE type = :type AND $select
                    ORDER BY name, id";
        $selectparams['type'] = \tool_reportbuilder\constants::TYPE_DATASOURCE;
        $reports = $DB->get_records_sql_menu($sql, $selectparams);
        return $reports;
    }

    /**
     * Returns user available shared reports.
     *
     * @return string[]
     */
    private function get_shared_reports(): array {
        return $this->get_tenant_reports(\tool_tenant\sharedspace::get_shared_space_id());
    }
}