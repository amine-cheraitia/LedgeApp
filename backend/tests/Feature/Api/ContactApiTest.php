<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Contact;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Entreprise $entreprise;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->entreprise = Entreprise::factory()->create();
    }

    public function test_admin_liste_les_contacts_d_une_entreprise(): void
    {
        Contact::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Benali',
            'est_principal' => true,
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/entreprises/{$this->entreprise->id}/contacts")
            ->assertOk()
            ->assertJsonFragment(['nom' => 'Benali', 'est_principal' => true]);
    }

    public function test_modifier_un_contact_via_une_autre_entreprise_renvoie_404(): void
    {
        // Le contact appartient a l'entreprise A ; le modifier via l'URL d'une entreprise B -> 404.
        $contact = Contact::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Benali',
            'est_principal' => true,
        ]);

        $autreEntreprise = Entreprise::factory()->create();

        $this->actingAs($this->admin)
            ->putJson("/api/v1/entreprises/{$autreEntreprise->id}/contacts/{$contact->id}", ['nom' => 'Piraté'])
            ->assertNotFound();

        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'nom' => 'Benali']);
    }

    public function test_admin_cree_un_contact(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$this->entreprise->id}/contacts", [
                'nom' => 'Kaci',
                'prenom' => 'Amine',
                'email' => 'amine@kaci.dz',
                'est_principal' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.nom', 'Kaci');

        $this->assertDatabaseHas('contacts', [
            'nom' => 'Kaci',
            'entreprise_id' => $this->entreprise->id,
        ]);
    }

    public function test_un_nouveau_contact_principal_retire_le_precedent(): void
    {
        $ancien = Contact::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Ancien',
            'est_principal' => true,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$this->entreprise->id}/contacts", [
                'nom' => 'Nouveau',
                'est_principal' => true,
            ])
            ->assertCreated();

        $this->assertFalse($ancien->fresh()->est_principal);
    }

    public function test_admin_met_a_jour_un_contact(): void
    {
        $contact = Contact::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Ancien',
            'est_principal' => false,
        ]);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/entreprises/{$this->entreprise->id}/contacts/{$contact->id}", [
                'nom' => 'Modifie',
                'est_principal' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.nom', 'Modifie')
            ->assertJsonPath('data.est_principal', true);
    }

    public function test_admin_supprime_un_contact(): void
    {
        $contact = Contact::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'A supprimer',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/entreprises/{$this->entreprise->id}/contacts/{$contact->id}")
            ->assertOk();

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }
}
