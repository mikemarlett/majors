<?php

declare(strict_types=1);

namespace Majors\Http;

use Majors\Http\Ajax\CatalogActions;
use Majors\Http\Ajax\CourseActions;
use Majors\Http\Ajax\DegreeMapActions;
use Majors\Http\Ajax\FootnoteActions;
use Majors\Http\Ajax\ProgramActions;
use Majors\Http\Ajax\UserActions;
use Majors\Support\Json;
use Majors\Support\Request;

/**
 * degree_maps/admin/ajax.php?action=<name> — one entry point for every admin
 * ajax call. Replaces the 27 stand-alone files: one bootstrap, one session
 * check, one role check, one HTTP-method check, one CSRF check, one JSON
 * error envelope. Handlers return an array (sent as JSON) or a string (sent
 * as HTML, for the modal-fragment endpoints the JS injects directly).
 */
final class AjaxKernel extends Controller
{
    /**
     * action => [class, method, roles, http method ('GET'|'POST'|'ANY'), csrf required]
     * super_admin satisfies every role list.
     */
    private const ROUTES = [
        // degree map header / hours / departments
        'get_degree_map'      => [DegreeMapActions::class, 'editorHtml',        ['advisor'],              'ANY',  false],
        'edit_map'            => [DegreeMapActions::class, 'editForm',          ['advisor'],              'ANY',  false],
        'new_map'             => [DegreeMapActions::class, 'newForm',           ['advisor'],              'ANY',  false],
        'save_map_details'    => [DegreeMapActions::class, 'saveDetails',       ['advisor'],              'POST', true],
        'edit_map_hours'      => [DegreeMapActions::class, 'hoursForm',         ['advisor'],              'ANY',  false],
        'save_map_hours'      => [DegreeMapActions::class, 'saveHours',         ['advisor'],              'POST', true],
        'delete_degree_map'   => [DegreeMapActions::class, 'delete',            ['super_admin'],          'POST', true],
        'clone_degree_map'    => [DegreeMapActions::class, 'clone',             ['advisor'],              'POST', true],
        'get_departments'     => [DegreeMapActions::class, 'departmentOptions', ['advisor', 'marketing'], 'ANY',  false],
        // courses
        'edit_course'         => [CourseActions::class,    'editForm',          ['advisor'],              'ANY',  false],
        'save_course'         => [CourseActions::class,    'save',              ['advisor'],              'POST', true],
        'delete_course'       => [CourseActions::class,    'delete',            ['advisor'],              'POST', true],
        'save_course_order'   => [CourseActions::class,    'saveOrder',         ['advisor'],              'POST', true],
        'course_autocomplete' => [CatalogActions::class,   'search',            ['advisor'],              'GET',  false],
        // footnotes
        'edit_map_footnotes'  => [FootnoteActions::class,  'editForm',          ['advisor'],              'ANY',  false],
        'save_map_footnotes'  => [FootnoteActions::class,  'save',              ['advisor'],              'POST', true],
        'delete_footnote'     => [FootnoteActions::class,  'delete',            ['advisor'],              'POST', true],
        'get_footnotes'       => [FootnoteActions::class,  'list',              ['advisor'],              'ANY',  false],
        // Majors editor (marketing; super admins implied)
        'program_search'      => [ProgramActions::class,   'search',            ['marketing'],            'GET',  false],
        'new_program'         => [ProgramActions::class,   'create',            ['marketing'],            'POST', true],
        'save_program'        => [ProgramActions::class,   'saveProgram',       ['marketing'],            'POST', true],
        'get_section_form'    => [ProgramActions::class,   'sectionForm',       ['marketing'],            'ANY',  false],
        'save_section'        => [ProgramActions::class,   'saveSection',       ['marketing'],            'POST', true],
        'detach_section'      => [ProgramActions::class,   'detachSection',     ['marketing'],            'POST', true],
        'delete_section'      => [ProgramActions::class,   'deleteSection',     ['marketing'],            'POST', true],
        'save_section_order'  => [ProgramActions::class,   'saveSectionOrder',  ['marketing'],            'POST', true],
        'save_similar'        => [ProgramActions::class,   'saveSimilar',       ['marketing'],            'POST', true],
        'get_block_form'      => [ProgramActions::class,   'blockForm',         ['marketing'],            'ANY',  false],
        'save_block'          => [ProgramActions::class,   'saveBlock',         ['marketing'],            'POST', true],
        'delete_block'        => [ProgramActions::class,   'deleteBlock',       ['marketing'],            'POST', true],

        // users (super admins only)
        'get_users'           => [UserActions::class,      'list',              ['super_admin'],          'GET',  false],
        'get_user_form'       => [UserActions::class,      'form',              ['super_admin'],          'ANY',  false],
        'save_user'           => [UserActions::class,      'save',              ['super_admin'],          'POST', true],
        'delete_user'         => [UserActions::class,      'delete',            ['super_admin'],          'POST', true],
    ];

    public function handle(Request $r): void
    {
        header('Cache-Control: no-store');
        $action = $r->str('action');
        $route  = self::ROUTES[$action] ?? null;
        if ($route === null) {
            Json::fail('Unknown action.', 404);
        }
        [$class, $method, $roles, $httpMethod, $csrf] = $route;

        if ($httpMethod !== 'ANY' && $r->method() !== $httpMethod) {
            Json::fail("This action requires {$httpMethod}.", 405);
        }
        $user = $this->app->guard()->requireAjax($roles, $csrf);

        $handler = new $class($this->app);
        try {
            $result = $handler->$method($r, $user);
        } catch (Ajax\DuplicateMapException $e) {
            Json::fail($e->getMessage(), $e->status, ['existing' => true, 'degree_map_id' => $e->existingId]);
        } catch (Ajax\ActionException $e) {
            Json::fail($e->getMessage(), $e->status);
        }
        if (is_string($result)) {
            header('Content-Type: text/html; charset=utf-8');
            echo $result;
            exit;
        }
        Json::send(is_array($result) ? $result : ['success' => true]);
    }
}
