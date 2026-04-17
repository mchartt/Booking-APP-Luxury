package com.smartdesk.backend.model;

import jakarta.persistence.*;

@Entity
public class Ticket {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long ticketId;

    private Long deskId;
    private String description;
    private String status;
    private String technicianNote;

    public void report(Desk desk) {
        this.deskId = desk.getDeskId();
        this.status = "OPEN";
    }

    public Long getTicketId() { return ticketId; }
    public void setTicketId(Long ticketId) { this.ticketId = ticketId; }
    public Long getDeskId() { return deskId; }
    public void setDeskId(Long deskId) { this.deskId = deskId; }
    public String getDescription() { return description; }
    public void setDescription(String description) { this.description = description; }
    public String getStatus() { return status; }
    public void setStatus(String status) { this.status = status; }
    public String getTechnicianNote() { return technicianNote; }
    public void setTechnicianNote(String technicianNote) { this.technicianNote = technicianNote; }
}
