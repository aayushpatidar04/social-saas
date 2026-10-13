<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class OrganizationAiPromptsTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    public function test_organization_admin_can_save_ai_prompts(): void
    {
        $organization = $this->org();
        $admin = $this->userIn($organization);
        $prompts = OllamaService::defaultPrompts();
        $prompts['response'] = 'Custom reply for {author_name}: {comment}';

        $this->actingAs($admin)
            ->patch('/settings/organization', [
                'name' => $organization->name,
                'ai_prompts' => $prompts,
            ])
            ->assertRedirect();

        $this->assertSame($prompts, $organization->fresh()->ai_prompts);
    }

    public function test_organization_prompts_are_selected_using_the_supplied_organization_id(): void
    {
        $first = $this->org(['ai_prompts' => ['intent' => 'First organization: {comment}']]);
        $second = $this->org(['ai_prompts' => ['intent' => 'Second organization: {comment}']]);
        $service = new class extends OllamaService {
            public string $capturedPrompt = '';

            protected function callOllama(string $prompt): string
            {
                $this->capturedPrompt = $prompt;

                return 'sales';
            }
        };

        $service->classifyIntent($first->id, 'Need pricing');
        $this->assertSame('First organization: Need pricing', $service->capturedPrompt);

        $service->classifyIntent($second->id, 'Need pricing');
        $this->assertSame('Second organization: Need pricing', $service->capturedPrompt);
    }

    public function test_non_admin_cannot_change_organization_prompts(): void
    {
        $organization = $this->org();
        $viewer = $this->userIn($organization, 'viewer');

        $this->actingAs($viewer)
            ->patch('/settings/organization', [
                'name' => $organization->name,
                'ai_prompts' => OllamaService::defaultPrompts(),
            ])
            ->assertForbidden();

        $this->assertSame(OllamaService::defaultPrompts(), $organization->fresh()->ai_prompts);
    }
}
