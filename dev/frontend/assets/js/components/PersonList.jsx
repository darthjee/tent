import React, { useEffect, useState } from "react";

const PersonList = () => {
  const [persons, setPersons] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    fetch("/api/persons")
      .then((res) => {
        if (!res.ok) throw new Error("Failed to fetch persons");
        return res.json();
      })
      .then((data) => {
        setPersons(data);
        setLoading(false);
      })
      .catch((err) => {
        setError(err.message);
        setLoading(false);
      });
  }, []);

  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;

  return (
    <div>
      <h2>Person List</h2>
      <ul>
        {persons.map((person) => (
          <li key={person.id}>
            {person.first_name} {person.last_name} ({person.birthdate})
          </li>
        ))}
      </ul>
    </div>
  );
};

export default PersonList;
