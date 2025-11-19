function TourCard({ tour }) {
  return (
    <div style={{ border: '1px solid #ddd', borderRadius: 10, padding: 10, width: 250 }}>
      <h3>{tour.name}</h3>
      <p>{tour.description}</p>
      <p><strong>Price:</strong> {tour.price} MAD</p>
    </div>
  );
}
export default TourCard;
