import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { contactsApi, type ContactPayload } from '@/api/modules/contacts'
import type { Contact } from '@/types'

export function useContacts() {
  const toast = useToast()
  const contacts = ref<Contact[]>([])
  const loading = ref(false)

  async function fetchContacts(entrepriseId: number) {
    loading.value = true
    try {
      const response = await contactsApi.getAll(entrepriseId)
      contacts.value = response.data
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les contacts.', life: 3000 })
    } finally {
      loading.value = false
    }
  }

  async function createContact(entrepriseId: number, data: ContactPayload) {
    const response = await contactsApi.create(entrepriseId, data)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Contact ajoute.', life: 3000 })
    await fetchContacts(entrepriseId)
    return response.data
  }

  async function updateContact(entrepriseId: number, contactId: number, data: Partial<ContactPayload>) {
    const response = await contactsApi.update(entrepriseId, contactId, data)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Contact mis a jour.', life: 3000 })
    await fetchContacts(entrepriseId)
    return response.data
  }

  async function deleteContact(entrepriseId: number, contactId: number) {
    await contactsApi.delete(entrepriseId, contactId)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Contact supprime.', life: 3000 })
    await fetchContacts(entrepriseId)
  }

  return {
    contacts,
    loading,
    fetchContacts,
    createContact,
    updateContact,
    deleteContact,
  }
}
