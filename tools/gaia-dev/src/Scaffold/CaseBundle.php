<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

use GaiaDev\Graph\RouteMatcher;
use GaiaDev\Query\GraphReader;
use stdClass;

/**
 * Builds the api-tester cases bundle A of docs/dev/bundles.md prescribes for one REST
 * endpoint, from the graph: the route and its permission, the profiles' verdicts, the
 * fixture placeholders that exist and the assertion names the Asserter knows.
 * `client` gets the happy path as the profile; `acl` gets, per profile with a rule on the
 * permission, the denial, the allowed read or write, or the member and non-member pair.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CaseBundle
{
    /**
     * HTTP method => permission action (RestController's action map).
     */
    private const ACTIONS = ['GET' => 'get', 'POST' => 'create', 'PUT' => 'update', 'PATCH' => 'update', 'DELETE' => 'delete'];

    private const VERDICTS = ['grants' => 'allow', 'grants_members' => 'members', 'denies' => 'deny'];

    private const ACL_SOURCE = 'acl-group';

    /**
     * @var GraphReader
     */
    private $graph;

    /**
     * @var string[] things the author still has to do by hand
     */
    private $notes = [];

    /**
     * @param GraphReader $graph
     */
    public function __construct(GraphReader $graph)
    {
        $this->graph = $graph;
    }

    /**
     * @return string[] hints about placeholders or seeds the bundle could not settle
     */
    public function notes(): array
    {
        return array_values(array_unique($this->notes));
    }

    /**
     * @param CaseRequest $request
     * @return array[] the cases, keys in the order of the target catalog
     * @throws ScaffoldException
     */
    public function build(CaseRequest $request): array
    {
        if (!in_array($request->catalog, ['client', 'acl'], true)) {
            throw new ScaffoldException("Cannot scaffold into catalog '{$request->catalog}'; use client or acl");
        }
        if (!isset(self::ACTIONS[$request->method])) {
            throw new ScaffoldException("Unsupported method {$request->method}");
        }
        $this->notes = [];
        $resource = $this->resource($request);
        $permission = $resource . '.' . self::ACTIONS[$request->method];
        if ($this->graph->node('permission', $permission) === null) {
            throw new ScaffoldException($this->graph->unknown('permission', $permission, array_keys($this->graph->nodes('permission'))));
        }
        $this->checkAssertions($request->assertions);

        if ($request->catalog === 'acl') {
            return $this->aclCases($request, $resource, $permission);
        }

        return [$this->happyCase($request, $resource, $request->profile ?: 'default', 'client')];
    }

    /**
     * @param CaseRequest $request
     * @return string the REST resource the endpoint belongs to
     * @throws ScaffoldException
     */
    private function resource(CaseRequest $request): string
    {
        $matcher = new RouteMatcher();
        foreach ($this->graph->nodes('route') as $key => $route) {
            $attributes = $route['attributes'];
            if (isset($attributes['path'], $attributes['method'])) {
                $matcher->add($key, $attributes['method'], $attributes['path']);
            }
        }
        $key = $matcher->match($request->method, $request->path);
        if ($key === null) {
            throw new ScaffoldException(
                $this->graph->unknown('route', $request->method . ' ' . $request->path, array_keys($this->graph->nodes('route')))
            );
        }
        $attributes = $this->graph->node('route', $key)['attributes'];
        if (($attributes['type'] ?? null) !== 'rest') {
            throw new ScaffoldException("{$key} is not a REST resource route; scaffold covers REST resources only");
        }

        return $attributes['name'];
    }

    /**
     * @param array $assertions name => value
     * @throws ScaffoldException
     */
    private function checkAssertions(array $assertions): void
    {
        foreach (array_keys($assertions) as $name) {
            $node = $this->graph->node('assertion', $name);
            if ($node === null || !isset($node['attributes']['source']) || strpos($name, '.') !== false) {
                $known = [];
                foreach ($this->graph->nodes('assertion') as $key => $assertion) {
                    if (isset($assertion['attributes']['source']) && strpos($key, '.') === false) {
                        $known[] = $key;
                    }
                }
                throw new ScaffoldException($this->graph->unknown('assertion', $name, $known));
            }
        }
    }

    /**
     * @param CaseRequest $request
     * @param string $resource
     * @param string $permission
     * @return array[]
     * @throws ScaffoldException
     */
    private function aclCases(CaseRequest $request, string $resource, string $permission): array
    {
        $profiles = $request->profile !== null ? [$this->graph->find('profile', $request->profile)] : $this->rulesOn($permission);
        if ($profiles === []) {
            throw new ScaffoldException("No profile besides default has a rule on {$permission}; pass --profile=<name>");
        }

        $cases = [];
        foreach ($profiles as $profile) {
            $verdict = $this->verdict($profile, $permission);
            if ($verdict === 'deny') {
                $cases[] = $this->deniedCase($request, $resource, $profile['key'], $this->resolvePath($request, $resource, false), 'denied');
                continue;
            }
            if ($verdict === null) {
                $this->notes[] = "{$profile['key']} has no explicit rule on {$permission}: check the allowed case against the role's real access";
            }
            $cases[] = $this->happyCase($request, $resource, $profile['key'], 'acl', $verdict === 'members' ? 'member-allowed' : 'allowed');
            if ($verdict === 'members') {
                $other = $this->otherProjectCase($request, $resource, $profile['key']);
                if ($other !== null) {
                    $cases[] = $other;
                }
            }
        }

        return $cases;
    }

    /**
     * @param string $permission
     * @return array[] the non-default profiles with a grant, member grant or denial on the permission
     */
    private function rulesOn(string $permission): array
    {
        $profiles = [];
        foreach ($this->graph->nodes('profile') as $key => $profile) {
            if ($key !== 'default' && $key !== 'anonymous' && $this->verdict($profile, $permission) !== null) {
                $profiles[] = $profile;
            }
        }

        return $profiles;
    }

    /**
     * @param array $profile
     * @param string $permission
     * @return string|null allow, members, deny, or null when the profile has no rule
     */
    private function verdict(array $profile, string $permission): ?string
    {
        foreach (self::VERDICTS as $edge => $verdict) {
            foreach ($this->graph->out($profile, $edge) as $target) {
                if ($target['key'] === $permission) {
                    return $verdict;
                }
            }
        }

        return null;
    }

    /**
     * @param CaseRequest $request
     * @param string $resource
     * @param string $profile
     * @param string $path
     * @param string $kind id suffix
     * @return array
     */
    private function deniedCase(CaseRequest $request, string $resource, string $profile, string $path, string $kind): array
    {
        $case = $this->withInput($this->head($request, $resource, $path, $profile, $kind), $request, $resource);
        $case['expects'] = ['status' => 403, 'shape' => 'error', 'errorEqual' => 'Access Denied'];
        $case['source'] = self::ACL_SOURCE;

        return $case;
    }

    /**
     * A member's access to a record of another project, when the seed has such a record.
     *
     * @param CaseRequest $request
     * @param string $resource
     * @param string $profile
     * @return array|null
     */
    private function otherProjectCase(CaseRequest $request, string $resource, string $profile): ?array
    {
        if (!$request->isDetail()) {
            return null;
        }
        $name = $resource === 'project' ? 'otherProjectId' : 'otherProject' . ucfirst($resource) . 'Id';
        if ($this->graph->node('fixture', $name) === null) {
            $this->notes[] = "no fixture {$name}: seed a record in another project and add the placeholder, then a non-member denial case";

            return null;
        }
        $path = preg_replace('/\{[^}]+\}/', '{' . $name . '}', $this->resolvePath($request, $resource, false));

        return $this->deniedCase($request, $resource, $profile, $path, 'denied-other-project');
    }

    /**
     * @param CaseRequest $request
     * @param string $resource
     * @param string $profile
     * @param string $catalog client or acl
     * @param string $kind id suffix (acl ids only)
     * @return array
     */
    private function happyCase(CaseRequest $request, string $resource, string $profile, string $catalog, string $kind = ''): array
    {
        $path = $this->resolvePath($request, $resource, true);
        $case = $this->head($request, $resource, $path, $profile, $kind);
        $expects = [];
        if ($request->method === 'DELETE') {
            $expects['status'] = [200, 204];
        } else {
            $expects['status'] = $request->method === 'POST' ? 201 : 200;
            $expects['shape'] = $request->method === 'GET' && !$request->isDetail() ? 'jsonapi.collection' : 'jsonapi.resource';
        }
        $expects = array_merge($expects, $request->assertions);

        if ($catalog === 'acl') {
            $case = $this->withInput($case, $request, $resource);
            $case['expects'] = $expects;
            $case['source'] = self::ACL_SOURCE;

            return $case;
        }
        if ($request->method !== 'GET' && $request->method !== 'DELETE') {
            $case['body'] = $this->body($resource);
        }
        if ($request->method === 'POST') {
            $case['store'] = ['runtime.' . $resource . 'Id' => 'data.id'];
        }
        $case['expects'] = $expects;
        if ($request->method === 'GET' && !$request->isDetail()) {
            $case['query'] = ['limit' => 5, 'page' => 1];
        }

        return $case;
    }

    /**
     * @param array $case
     * @param CaseRequest $request
     * @param string $resource
     * @return array the case with the query (collection GET) or body (writes) the method needs
     */
    private function withInput(array $case, CaseRequest $request, string $resource): array
    {
        if ($request->method === 'GET' && !$request->isDetail()) {
            $case['query'] = ['limit' => 5, 'page' => 1];
        }
        if ($request->method !== 'GET' && $request->method !== 'DELETE') {
            $case['body'] = $this->body($resource);
        }

        return $case;
    }

    /**
     * @param string $resource
     * @return array a JSON:API body with no attributes yet
     */
    private function body(string $resource): array
    {
        return ['data' => ['type' => $resource, 'attributes' => new stdClass()]];
    }

    /**
     * @param CaseRequest $request
     * @param string $resource
     * @param string $path
     * @param string $profile
     * @param string $kind
     * @return array id, method, path, resource, auth
     */
    private function head(CaseRequest $request, string $resource, string $path, string $profile, string $kind): array
    {
        return [
            'id' => $this->id($request, $resource, $path, $profile, $kind),
            'method' => $request->method,
            'path' => $path,
            'resource' => $resource,
            'auth' => $profile === 'default' ? true : $profile,
        ];
    }

    /**
     * Client ids follow the path (`get-comment-api-v1-comment-runtime-id`); acl ids say what is expected and for whom
     * (`acl-issue-denied-without-project-group`).
     *
     * @param CaseRequest $request
     * @param string $resource
     * @param string $path
     * @param string $profile
     * @param string $kind
     * @return string
     */
    private function id(CaseRequest $request, string $resource, string $path, string $profile, string $kind): string
    {
        if ($request->catalog === 'acl') {
            $detail = $request->isDetail() ? '-detail' : '';
            $method = $request->method === 'GET' ? '' : '-' . strtolower($request->method);

            return 'acl-' . $resource . $detail . $method . '-' . $kind . '-' . $this->kebab($profile);
        }
        $slug = preg_replace(['/\{runtime\.[^}]+\}/', '/\{[^}]+\}/'], ['runtime-id', 'id'], $path);

        return strtolower($request->method) . '-' . $resource . '-' . trim(preg_replace('/[^a-z0-9]+/i', '-', $slug), '-');
    }

    /**
     * @param string $name
     * @return string aclNoProject => acl-no-project
     */
    private function kebab(string $name): string
    {
        return strtolower(preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '-', $name));
    }

    /**
     * Names each path placeholder after the segment before it: a fixture `<segment>Id` when the seed has one,
     * else `runtime.<segment>Id` (a record an earlier POST case stores).
     *
     * @param CaseRequest $request
     * @param string $resource
     * @param bool $note whether to note a runtime id nothing stores
     * @return string
     */
    private function resolvePath(CaseRequest $request, string $resource, bool $note): string
    {
        $segments = explode('/', $request->path);
        foreach ($segments as $i => $segment) {
            if (!preg_match('/^\{[^}]+\}$/', $segment)) {
                continue;
            }
            $previous = $i > 0 ? $segments[$i - 1] : '';
            $name = ($previous !== '' && $previous[0] !== '{' ? $previous : $resource) . 'Id';
            if ($this->graph->node('fixture', $name) !== null) {
                $segments[$i] = '{' . $name . '}';
                continue;
            }
            $segments[$i] = '{runtime.' . $name . '}';
            if ($note && !$this->stored('runtime.' . $name)) {
                $this->notes[] = "no case stores runtime.{$name}: add a POST case with \"store\", or a fixture {$name}";
            }
        }

        return implode('/', $segments);
    }

    /**
     * @param string $key e.g. runtime.issueId
     * @return bool whether some catalog case stores it
     */
    private function stored(string $key): bool
    {
        foreach ($this->graph->nodes('case') as $case) {
            if (in_array($key, isset($case['attributes']['stores']) ? $case['attributes']['stores'] : [], true)) {
                return true;
            }
        }

        return false;
    }
}
