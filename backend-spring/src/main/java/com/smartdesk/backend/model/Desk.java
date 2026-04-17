package com.smartdesk.backend.model;

import com.smartdesk.backend.state.AvailableState;
import com.smartdesk.backend.state.DeskState;
import jakarta.persistence.*;

import java.util.ArrayList;
import java.util.List;

@Entity
public class Desk {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long deskId;

    @ElementCollection
    private List<String> amenities = new ArrayList<>();

    private String building;

    @Transient
    private DeskState currentState = new AvailableState();

    @Column(nullable = false)
    private String stateName = "AVAILABLE";

    public void changeState(DeskState state) {
        this.currentState = state;
    }

    public void handleState() {
        getCurrentState().handleState(this);
    }

    public DeskState getCurrentState() {
        if (currentState == null) {
            currentState = DeskState.from(stateName);
        }
        return currentState;
    }

    public Long getDeskId() { return deskId; }
    public void setDeskId(Long deskId) { this.deskId = deskId; }
    public List<String> getAmenities() { return amenities; }
    public void setAmenities(List<String> amenities) { this.amenities = amenities; }
    public String getBuilding() { return building; }
    public void setBuilding(String building) { this.building = building; }
    public String getStateName() { return stateName; }
    public void setStateName(String stateName) { this.stateName = stateName; }
}
