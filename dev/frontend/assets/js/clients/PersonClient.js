class PersonClient {
  /**
   * Fetches the list of all persons.
   *
   * @returns {Promise<Object[]>} Resolves with the array of person objects.
   * @throws {Error} If the request fails.
   */
  async list() {
    const response = await fetch('/persons');
    if (!response.ok) {
      throw new Error('Failed to fetch persons data');
    }
    return response.json();
  }

  /**
   * Creates a new person.
   *
   * @param {Object} data - The person attributes to create.
   * @returns {Promise<Object>} Resolves with the created person object.
   * @throws {Error} If the request fails.
   */
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

  /**
   * Uploads a photo for the given person.
   *
   * Sends a POST request to `/persons/:id/photo` with the file as
   * `multipart/form-data` (field name `photo`).
   *
   * @param {number|string} id   - The ID of the person.
   * @param {File}          file - The JPEG image file to upload.
   * @returns {Promise<Object>} Resolves with the updated person object.
   * @throws {Error} If the upload request fails.
   */
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