package com.smartdesk.backend.model;

import jakarta.persistence.Column;
import jakarta.persistence.Entity;

@Entity
public class Host extends User {

    @Column(nullable = false)
    private String nameStructure;

    @Column(nullable = false)
    private String description;

    public String getNameStructure() { return nameStructure; }
    public void setNameStructure(String nameStructure) { this.nameStructure = nameStructure; }
    public String getDescription() { return description; }
    public void setDescription(String description) { this.description = description; }
}
