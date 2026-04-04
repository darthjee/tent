class PersonClient {
  async list() {
    const response = await fetch('/persons');
    if (!response.ok) {
      throw new Error('Failed to fetch persons data');
    }
    return response.json();
  }

  async create(data) {
    const response = await fetch('/persons', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
    if (!response.ok) {
      throw new Error('Failed to create person');
    }
    return response.json();
  }

  async uploadPhoto(id, file) {
    const formData = new FormData();
    formData.append('photo', file);
    const response = await fetch(`/persons/${id}/photo`, {
      method: 'POST',
      body: formData,
    });
    if (!response.ok) {
      throw new Error('Failed to upload photo');
    }
    return response.json();
  }
}

// Export class for testing or custom instances
export { PersonClient };