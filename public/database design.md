```mermaid
erDiagram
	
	Users {
		int id PK
		string username
		string email
		string password
		string role
		string api_key
	}
	
	Books { 
	int id PK
	string isbn UK
	string title 
	string author  
	string genre 
	int pages 
	string language 
	string publisher 
	string format
	double price
	int quantity
	int year
}

Transactions {
	int book_id FK
	double price
	int quantity
	date sold_date
}
 
Books ||--|{ Transactions: sold
```