<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketAttachment>
 */
class TicketAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_ticket_id' => SupportTicket::factory(),
            'uploaded_by' => User::factory(),
            'path' => 'tickets/example.pdf',
            'name' => 'example.pdf',
        ];
    }
}
