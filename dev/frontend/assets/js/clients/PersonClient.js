class PersonClient {
  async list() {
    const response = await fetch('/persons');
    if (!response.ok) {
      throw new Error('Failed to fetch persons data');
    }
    return response.json();
  }
}

// Export class for testing or custom instances
export { PersonClient };