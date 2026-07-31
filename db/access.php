<?php
/**
 * Capability definitions for AI Grader Dashboard Block
 *
 * @package    block_aigrader_dashboard
 * @copyright  2024 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // For adding block to course pages - MUST be CONTEXT_COURSE (not CONTEXT_BLOCK!)
    'block/aigrader_dashboard:addinstance' => [
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,  // CRITICAL: Must be CONTEXT_COURSE for blocks
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/site:manageblocks',
    ],
    // For adding block to Dashboard/My page - CONTEXT_SYSTEM with 'user' archetype
    'block/aigrader_dashboard:myaddinstance' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,  // All authenticated users can ADD the block
        ],
        'clonepermissionsfrom' => 'moodle/my:manageblocks',
    ],
    // For admin override to see all courses' ungraded essays
    'block/aigrader_dashboard:viewall' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];
