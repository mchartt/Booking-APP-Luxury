package com.smartdesk.backend.controller;

import com.smartdesk.backend.model.Desk;
import com.smartdesk.backend.model.Ticket;
import com.smartdesk.backend.service.TicketService;
import org.springframework.web.bind.annotation.*;

import java.util.List;

@RestController
@RequestMapping("/api/technician")
public class TechnicianController {

    private final TicketService ticketService;

    public TechnicianController(TicketService ticketService) {
        this.ticketService = ticketService;
    }

    @GetMapping("/tickets")
    public List<Ticket> getPendingTickets() {
        return ticketService.getPendingTickets();
    }

    @PatchMapping("/tickets/{ticketId}")
    public Ticket updateTicketStatus(@PathVariable Long ticketId, @RequestParam String status, @RequestParam(required = false) String note) {
        return ticketService.resolveTicket(ticketId, status + (note == null ? "" : " - " + note));
    }

    @PostMapping("/desks/{deskId}/maintenance")
    public Desk setMaintenanceMode(@PathVariable Long deskId) {
        return ticketService.setDeskMaintenance(deskId);
    }
}
