import React, { useEffect, useState } from 'react';
import { PersonClient } from '../clients/PersonClient';

const PersonList = () => {
  const [persons, setPersons] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    (new PersonClient()).list()
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
