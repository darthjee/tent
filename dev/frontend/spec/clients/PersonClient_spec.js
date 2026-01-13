import PersonClient from "../../assets/js/clients/PersonClient.js";

describe("PersonClient", () => {
  beforeEach(() => {
    global.fetch = jest.fn();
  });

  afterEach(() => {
    jest.resetAllMocks();
  });

  it("calls /persons and returns data", async () => {
    const mockData = [
      { id: 1, first_name: "Alice", last_name: "Smith", birthdate: "1991-01-01" },
      { id: 2, first_name: "Bob", last_name: "Jones", birthdate: "1992-02-02" }
    ];
    global.fetch.mockResolvedValue({
      ok: true,
      json: async () => mockData
    });

    const result = await PersonClient.list();
    expect(global.fetch).toHaveBeenCalledWith("/persons");
    expect(result).toEqual(mockData);
  });

  it("throws error if fetch fails", async () => {
    global.fetch.mockResolvedValue({ ok: false });
    await expect(PersonClient.list()).rejects.toThrow("Failed to fetch persons");
  });
});
