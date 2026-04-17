package com.smartdesk.backend.model;

import jakarta.persistence.*;

@Entity
public class Space {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long spaceId;

    private String description;

    private Long hostId;

    private String name;

    public Long getSpaceId() { return spaceId; }
    public void setSpaceId(Long spaceId) { this.spaceId = spaceId; }
    public String getDescription() { return description; }
    public void setDescription(String description) { this.description = description; }
    public Long getHostId() { return hostId; }
    public void setHostId(Long hostId) { this.hostId = hostId; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
}
