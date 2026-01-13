class PersonClient {
  static async list() {
    const res = await fetch("/persons");
    if (!res.ok) throw new Error("Failed to fetch persons");
    return await res.json();
  }
}

export default PersonClient;
