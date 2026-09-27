<?php

namespace App\Mcp\Tools;

use App\Http\Resources\MemberResource;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_members')]
#[IsReadOnly]
#[Description('List the people in the workspace (id, name, email, role). Use the id as user_id when assigning tasks.')]
class ListMembers extends Tool
{
    public function handle(Request $request): Response
    {
        $members = $request->user()->tenant->users()->orderBy('name')->get();

        return Response::json([
            'members' => MemberResource::collection($members)->resolve(),
        ]);
    }
}
