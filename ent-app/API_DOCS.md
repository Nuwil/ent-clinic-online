# API Documentation

## Base URL
```
http://localhost/ENT-clinic-online/ent-app/public/api
```

## Authentication
All API requests include standard HTTP headers. Current implementation uses session-based auth.

## Endpoints

### Patients
- `GET /api/patients` - List all patients
- `GET /api/patients/:id` - Get single patient
- `POST /api/patients` - Create patient
- `PUT /api/patients/:id` - Update patient
- `DELETE /api/patients/:id` - Delete patient

### Visits
- `GET /api/visits?patient_id=:id` - List patient visits
- `GET /api/visits/:id` - Get single visit
- `POST /api/visits` - Create visit
- `PUT /api/visits/:id` - Update visit
- `DELETE /api/visits/:id` - Delete visit

### Analytics
- `GET /api/analytics` - Get analytics data (ENT distribution, weekly visits)

### Health Check
- `GET /api/health` - API health status

## Response Format
All responses are JSON:
```json
{
  "status": "success|error",
  "data": {},
  "message": "Response message"
}
```

## Error Codes
- 200: Success
- 201: Created
- 400: Bad Request
- 404: Not Found
- 422: Validation Error
- 500: Server Error
